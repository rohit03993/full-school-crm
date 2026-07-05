<?php

namespace Tests\Unit;

use App\Support\MetaWhatsAppTemplateParser;
use Tests\TestCase;

class MetaWhatsAppTemplateParserTest extends TestCase
{
    public function test_parses_positional_body_variables(): void
    {
        $parsed = MetaWhatsAppTemplateParser::parse([
            [
                'type' => 'BODY',
                'text' => 'Hello {{1}}, your ward {{2}} checked in at {{3}}.',
            ],
        ]);

        $this->assertSame(3, $parsed['param_count']);
        $this->assertSame(['1', '2', '3'], $parsed['body_variables']);
    }

    public function test_parses_named_body_variables(): void
    {
        $parsed = MetaWhatsAppTemplateParser::parse([
            [
                'type' => 'BODY',
                'text' => 'Hi {{student_name}}, roll {{roll_number}}.',
            ],
        ]);

        $this->assertSame(2, $parsed['param_count']);
        $this->assertSame(['student_name', 'roll_number'], $parsed['body_variables']);
    }
}
