<?php

namespace Tests\Unit;

use App\Services\WhatsAppTemplateParamResolver;
use Tests\TestCase;

class WhatsAppTemplateParamResolverPreviewTest extends TestCase
{
    public function test_it_fills_positional_placeholders(): void
    {
        $preview = app(WhatsAppTemplateParamResolver::class)->buildPreview(
            'Dear Parent, {{1}} checked in at {{2}} on {{3}}.',
            ['Aarav', '09:15 AM', '09 Sep 2026'],
        );

        $this->assertSame(
            'Dear Parent, Aarav checked in at 09:15 AM on 09 Sep 2026.',
            $preview,
        );
    }

    public function test_it_fills_named_placeholders_from_body(): void
    {
        $preview = app(WhatsAppTemplateParamResolver::class)->buildPreview(
            'Dear Parent, {{student_name}} roll {{roll_number}} at {{check_in_time}} on {{date}}.',
            ['DUSHYANT', '12', '12:33 PM', '09 Sep 2026'],
        );

        $this->assertSame(
            'Dear Parent, DUSHYANT roll 12 at 12:33 PM on 09 Sep 2026.',
            $preview,
        );
    }

    public function test_it_fills_named_placeholders_from_body_variables_list(): void
    {
        $preview = app(WhatsAppTemplateParamResolver::class)->buildPreview(
            'Ward {{student_name}} / {{roll_number}}',
            ['Aarav', '2017'],
            ['student_name', 'roll_number'],
        );

        $this->assertSame('Ward Aarav / 2017', $preview);
    }

    public function test_it_fills_named_placeholders_even_when_body_variables_are_positional(): void
    {
        $preview = app(WhatsAppTemplateParamResolver::class)->buildPreview(
            'Dear Parent, {{student_name}} roll {{roll_number}} at {{check_out_time}} on {{date}}.',
            ['PRANAV', '99', '14:18', '2026-09-09'],
            ['1', '2', '3', '4'], // stale Meta meta — must not block body fill
        );

        $this->assertSame(
            'Dear Parent, PRANAV roll 99 at 14:18 on 2026-09-09.',
            $preview,
        );
        $this->assertStringNotContainsString('{{', $preview);
    }

    public function test_it_escapes_dollar_signs_in_replacement_values(): void
    {
        $preview = app(WhatsAppTemplateParamResolver::class)->buildPreview(
            'Fee due {{1}}',
            ['$500'],
        );

        $this->assertSame('Fee due $500', $preview);
    }
}
