<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Services\WebPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebPushServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_key_endpoint_reports_disabled_without_vapid(): void
    {
        config([
            'webpush.enabled' => true,
            'webpush.vapid.public_key' => null,
            'webpush.vapid.private_key' => null,
        ]);

        $this->getJson(route('pwa.push.public-key'))
            ->assertOk()
            ->assertJsonPath('enabled', false);
    }

    public function test_staff_can_save_a_push_subscription_when_configured(): void
    {
        config([
            'webpush.enabled' => true,
            'webpush.vapid.public_key' => 'BPublicTestKeyxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
            'webpush.vapid.private_key' => 'PrivateTestKeyxxxxxxxxxxxxxxxxxxxxxxx',
        ]);

        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->postJson(route('pwa.push.subscribe'), [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-1',
                'keys' => [
                    'p256dh' => 'p256dh-key',
                    'auth' => 'auth-key',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'audience' => 'staff',
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-1',
        ]);
    }

    public function test_portal_session_can_save_a_student_subscription(): void
    {
        config([
            'webpush.enabled' => true,
            'webpush.vapid.public_key' => 'BPublicTestKeyxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
            'webpush.vapid.private_key' => 'PrivateTestKeyxxxxxxxxxxxxxxxxxxxxxxx',
        ]);

        $student = Student::query()->create([
            'name' => 'Portal Student',
            'mobile' => '9876500011',
        ]);

        $this->withSession(['student_portal_id' => $student->id])
            ->postJson(route('pwa.push.subscribe'), [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/portal-endpoint',
                'keys' => ['p256dh' => 'a', 'auth' => 'b'],
            ])
            ->assertOk();

        $this->assertDatabaseHas('push_subscriptions', [
            'student_id' => $student->id,
            'audience' => 'portal',
        ]);
    }

    public function test_fee_reminder_push_respects_setting_toggle(): void
    {
        Setting::setValue('push.fee_reminders_enabled', '0', 'push');

        $sent = app(WebPushService::class)->notifyFeeReminders(collect([
            ['student_id' => 1, 'pending' => 500],
        ]));

        $this->assertSame(0, $sent);
    }

    public function test_unsubscribe_removes_endpoint(): void
    {
        PushSubscription::query()->create([
            'endpoint' => 'https://example.test/push/1',
            'audience' => 'staff',
        ]);

        $this->postJson(route('pwa.push.unsubscribe'), [
            'endpoint' => 'https://example.test/push/1',
        ])->assertOk();

        $this->assertDatabaseMissing('push_subscriptions', [
            'endpoint' => 'https://example.test/push/1',
        ]);
    }
}
