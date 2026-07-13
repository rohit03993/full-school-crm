<?php

namespace Tests\Unit;

use App\Support\WhatsAppTemplateParams;
use Tests\TestCase;

class WhatsAppTemplateParamsTest extends TestCase
{
    public function test_normalize_pads_and_replaces_blank_values(): void
    {
        $params = WhatsAppTemplateParams::normalize(['Rohit', ''], 4);

        $this->assertSame(['Rohit', '—', '—', '—'], $params);
    }
}
