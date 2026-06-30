<?php

namespace Tests\Unit;

use App\Support\PunchWhatsappStatus;
use Tests\TestCase;

class PunchWhatsappStatusTest extends TestCase
{
    public function test_chip_labels_for_common_statuses(): void
    {
        $this->assertSame('Sent', PunchWhatsappStatus::chip('success')['label']);
        $this->assertSame('success', PunchWhatsappStatus::chip('sent')['tone']);
        $this->assertSame('Failed', PunchWhatsappStatus::chip('failed')['label']);
        $this->assertSame('Queued', PunchWhatsappStatus::chip('queued')['label']);
        $this->assertSame('Not sent', PunchWhatsappStatus::chip(null)['label']);
    }
}
