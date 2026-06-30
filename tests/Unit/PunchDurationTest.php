<?php

namespace Tests\Unit;

use App\Support\PunchDuration;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PunchDurationTest extends TestCase
{
    #[Test]
    public function it_formats_minutes_only(): void
    {
        $this->assertSame('45m', PunchDuration::format('2026-06-18', '09:00', '09:45'));
    }

    #[Test]
    public function it_formats_hours_and_minutes(): void
    {
        $this->assertSame('2h 15m', PunchDuration::format('2026-06-18', '09:00:00', '11:15:00'));
    }

    #[Test]
    public function it_returns_null_when_out_is_missing(): void
    {
        $this->assertNull(PunchDuration::format('2026-06-18', '09:00', null));
    }

    #[Test]
    public function it_returns_null_when_out_is_not_after_in(): void
    {
        $this->assertNull(PunchDuration::format('2026-06-18', '11:00', '09:00'));
    }
}
