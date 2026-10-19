<?php

namespace Tests\Feature;

use App\Models\MetaWhatsAppTemplate;
use App\Models\Setting;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppLiveCampaign;
use App\Models\WhatsAppTemplate;
use App\Services\MetaWhatsAppTemplateDeleteService;
use App\Services\MetaWhatsAppTemplateUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class MetaWhatsAppTemplateEditDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'meta_whatsapp.graph_version' => 'v20.0',
            'meta_whatsapp.waba_id' => 'waba-1',
            'meta_whatsapp.access_token' => 'meta-test-token',
        ]);
    }

    public function test_new_words_are_sent_for_approval_and_other_templates_stay(): void
    {
        $template = $this->template('fee_due', 'Hello {{1}}, fee is due.');
        $other = $this->template('punch_in', 'Hello {{1}}, checked in.');
        WhatsAppTemplate::query()->create([
            'name' => 'fee_due',
            'body' => 'Hello {{1}}, fee is due.',
            'param_count' => 1,
            'param_mappings' => ['student.name'],
            'is_active' => true,
        ]);

        Http::fake([
            'https://graph.facebook.com/v20.0/901' => Http::response(['success' => true], 200),
        ]);

        $updated = app(MetaWhatsAppTemplateUpdateService::class)->reviseWording(
            $template,
            'Hello {{1}}, the fee is due today.',
            ['Aarav'],
            ['student.name'],
        );

        $this->assertSame('PENDING', $updated->status);
        $this->assertFalse($updated->is_active);
        $this->assertSame('Hello {{1}}, the fee is due today.', $updated->body);
        $this->assertSame('fee_due', $updated->name);
        $this->assertFalse(WhatsAppTemplate::query()->where('name', 'fee_due')->value('is_active'));
        $this->assertSame('Hello {{1}}, checked in.', $other->refresh()->body);
        $this->assertSame('APPROVED', $other->status);

        Http::assertSent(function ($request): bool {
            if ($request->url() !== 'https://graph.facebook.com/v20.0/901' || $request->method() !== 'POST') {
                return false;
            }

            $components = $request->data()['components'] ?? [];

            return ($components[0]['type'] ?? '') === 'HEADER'
                && ($components[0]['text'] ?? '') === 'School'
                && ($components[1]['text'] ?? '') === 'Hello {{1}}, the fee is due today.'
                && ! array_key_exists('name', $request->data());
        });
    }

    public function test_whatsapp_rejection_keeps_the_old_words(): void
    {
        $template = $this->template('fee_due', 'Hello {{1}}, fee is due.');

        Http::fake([
            'https://graph.facebook.com/v20.0/901' => Http::response([
                'error' => ['message' => 'Template is pending review', 'code' => 100],
            ], 400),
        ]);

        try {
            app(MetaWhatsAppTemplateUpdateService::class)->reviseWording(
                $template,
                'Hello {{1}}, the fee is due today.',
                ['Aarav'],
                ['student.name'],
            );
            $this->fail('Expected WhatsApp to reject the wording change.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('pending review', $exception->getMessage());
        }

        $this->assertSame('Hello {{1}}, fee is due.', $template->refresh()->body);
        $this->assertSame('APPROVED', $template->status);
        $this->assertTrue($template->is_active);
    }

    public function test_pending_and_photo_templates_are_not_sent_again(): void
    {
        Http::fake();

        $pending = $this->template('fee_due', 'Hello {{1}}, fee is due.', 'PENDING');
        $photo = $this->template('poster_note', 'Hello parents, see the photo.', 'APPROVED', [
            ['type' => 'HEADER', 'format' => 'IMAGE'],
            ['type' => 'BODY', 'text' => 'Hello parents, see the photo.'],
        ]);

        foreach ([$pending, $photo] as $template) {
            try {
                app(MetaWhatsAppTemplateUpdateService::class)->reviseWording(
                    $template,
                    'Hello parents, this is a new sentence.',
                    [],
                    [],
                );
                $this->fail('Locked template was sent.');
            } catch (InvalidArgumentException) {
                $this->assertSame($template->body, $template->refresh()->body);
            }
        }

        Http::assertNothingSent();
    }

    public function test_delete_removes_one_language_and_hides_it_from_sending(): void
    {
        $template = $this->template('old_note', 'Hello parents, this note is retired.');
        WhatsAppTemplate::query()->create([
            'name' => 'old_note',
            'body' => 'Hello parents, this note is retired.',
            'is_active' => true,
        ]);

        Http::fake([
            'https://graph.facebook.com/v20.0/waba-1/message_templates*' => Http::response(['success' => true], 200),
        ]);

        app(MetaWhatsAppTemplateDeleteService::class)->delete($template);

        $this->assertDatabaseMissing('meta_whatsapp_templates', ['name' => 'old_note']);
        $this->assertDatabaseHas('whatsapp_templates', [
            'name' => 'old_note',
            'is_active' => false,
        ]);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'DELETE'
                && str_contains($request->url(), 'https://graph.facebook.com/v20.0/waba-1/message_templates')
                && str_contains($request->url(), 'name=old_note')
                && str_contains($request->url(), 'hsm_id=901');
        });
    }

    public function test_delete_is_blocked_while_a_setting_or_campaign_still_uses_it(): void
    {
        Http::fake();

        $template = $this->template('fee_due', 'Hello {{1}}, fee is due.');
        $local = WhatsAppTemplate::query()->create([
            'name' => 'fee_due',
            'body' => 'Hello {{1}}, fee is due.',
            'is_active' => true,
        ]);
        Setting::setValue('whatsapp.fee_reminder_template_id', (string) $local->id, 'whatsapp');
        WhatsAppLiveCampaign::query()->create([
            'name' => 'Fee blast',
            'meta_whatsapp_template_id' => $template->id,
            'status' => 'live',
        ]);
        WhatsAppCampaign::query()->create([
            'name' => 'April fees',
            'whatsapp_template_id' => $local->id,
            'status' => 'running',
        ]);

        try {
            app(MetaWhatsAppTemplateDeleteService::class)->delete($template);
            $this->fail('A template that is still in use was deleted.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Fee reminder', $exception->getMessage());
            $this->assertStringContainsString('Fee blast', $exception->getMessage());
            $this->assertStringContainsString('April fees', $exception->getMessage());
        }

        $this->assertDatabaseHas('meta_whatsapp_templates', ['id' => $template->id]);
        $this->assertDatabaseHas('whatsapp_live_campaigns', ['name' => 'Fee blast']);
        $this->assertTrue($local->refresh()->is_active);
        Http::assertNothingSent();
    }

    public function test_other_language_of_the_same_name_can_still_be_sent(): void
    {
        $english = $this->template('fee_due', 'Hello {{1}}, fee is due.', 'APPROVED', [
            ['type' => 'BODY', 'text' => 'Hello {{1}}, fee is due.'],
        ], 'en');
        $this->template('fee_due', 'Namaste {{1}}, fees due hai.', 'APPROVED', [
            ['type' => 'BODY', 'text' => 'Namaste {{1}}, fees due hai.'],
        ], 'hi', '902');
        WhatsAppTemplate::query()->create([
            'name' => 'fee_due',
            'body' => 'Hello {{1}}, fee is due.',
            'is_active' => true,
        ]);

        Http::fake([
            'https://graph.facebook.com/v20.0/waba-1/message_templates*' => Http::response(['success' => true], 200),
        ]);

        app(MetaWhatsAppTemplateDeleteService::class)->delete($english);

        $this->assertDatabaseMissing('meta_whatsapp_templates', ['language' => 'en', 'name' => 'fee_due']);
        $this->assertDatabaseHas('meta_whatsapp_templates', ['language' => 'hi', 'name' => 'fee_due']);
        $this->assertTrue(WhatsAppTemplate::query()->where('name', 'fee_due')->value('is_active'));
    }

    /**
     * @param  list<array<string, mixed>>|null  $components
     */
    protected function template(
        string $name,
        string $body,
        string $status = 'APPROVED',
        ?array $components = null,
        string $language = 'en',
        string $metaId = '901',
    ): MetaWhatsAppTemplate {
        $components ??= [
            ['type' => 'HEADER', 'format' => 'TEXT', 'text' => 'School'],
            ['type' => 'BODY', 'text' => $body],
        ];

        return MetaWhatsAppTemplate::query()->create([
            'name' => $name,
            'language' => $language,
            'status' => $status,
            'param_count' => 1,
            'param_mappings' => ['student.name'],
            'body' => $body,
            'components' => $components,
            'provider_meta' => [
                'id' => $metaId,
                'category' => 'UTILITY',
                'header_format' => 'TEXT',
            ],
            'is_active' => $status === 'APPROVED',
            'synced_at' => now(),
        ]);
    }
}
