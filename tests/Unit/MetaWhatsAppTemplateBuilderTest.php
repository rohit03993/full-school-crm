<?php

namespace Tests\Unit;

use App\Support\MetaWhatsAppTemplateBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MetaWhatsAppTemplateBuilderTest extends TestCase
{
    public function test_builds_body_without_variables(): void
    {
        $payload = MetaWhatsAppTemplateBuilder::buildCreatePayload(
            'hello_parents',
            'en',
            'UTILITY',
            'School will remain closed tomorrow.',
        );

        $this->assertSame('hello_parents', $payload['name']);
        $this->assertCount(1, $payload['components']);
        $this->assertSame('BODY', $payload['components'][0]['type']);
        $this->assertArrayNotHasKey('parameter_format', $payload);
    }

    public function test_builds_positional_body_with_examples(): void
    {
        $payload = MetaWhatsAppTemplateBuilder::buildCreatePayload(
            'parent_checkin',
            'en',
            'UTILITY',
            'Hello {{1}}, your child checked in at {{2}}.',
            null,
            null,
            'Rohit Sharma, 9:15 AM',
        );

        $this->assertSame('positional', $payload['parameter_format']);
        $this->assertSame(
            [['Rohit Sharma', '9:15 AM']],
            $payload['components'][0]['example']['body_text'],
        );
    }

    public function test_rejects_missing_examples_when_body_has_variables(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MetaWhatsAppTemplateBuilder::buildCreatePayload(
            'missing_examples',
            'en',
            'UTILITY',
            'Hello {{1}}',
        );
    }

    public function test_normalizes_template_name(): void
    {
        $this->assertSame('parent_check_in', MetaWhatsAppTemplateBuilder::normalizeName('Parent Check In'));
    }
}
