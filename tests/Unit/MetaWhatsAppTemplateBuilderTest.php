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

    public function test_login_otp_name_submits_authentication_copy_code_payload(): void
    {
        $payload = MetaWhatsAppTemplateBuilder::buildCreatePayload(
            'login_otp',
            'en',
            'UTILITY',
            'Your login code is {{1}}. Do not share this code with anyone. It expires in 5 minutes.',
            null,
            null,
            '4821',
        );

        $this->assertSame('AUTHENTICATION', $payload['category']);
        $this->assertFalse($payload['allow_category_change']);
        $this->assertSame(300, $payload['message_send_ttl_seconds']);
        $this->assertSame('BODY', $payload['components'][0]['type']);
        $this->assertTrue($payload['components'][0]['add_security_recommendation']);
        $this->assertSame(5, $payload['components'][1]['code_expiration_minutes']);
        $this->assertSame('OTP', $payload['components'][2]['buttons'][0]['type']);
        $this->assertSame('COPY_CODE', $payload['components'][2]['buttons'][0]['otp_type']);
        $this->assertArrayNotHasKey('parameter_format', $payload);
    }

    public function test_authentication_category_does_not_require_custom_body(): void
    {
        $payload = MetaWhatsAppTemplateBuilder::buildCreatePayload(
            'staff_login_otp',
            'en_US',
            'AUTHENTICATION',
            '',
        );

        $this->assertSame('AUTHENTICATION', $payload['category']);
        $this->assertSame('staff_login_otp', $payload['name']);
    }
}
