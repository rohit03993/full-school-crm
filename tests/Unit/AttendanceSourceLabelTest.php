<?php

namespace Tests\Unit;

use App\Support\AttendanceSourceLabel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttendanceSourceLabelTest extends TestCase
{
    #[Test]
    public function it_labels_known_sources(): void
    {
        $this->assertSame('Biometric', AttendanceSourceLabel::for('biometric'));
        $this->assertSame('Manual IN/OUT', AttendanceSourceLabel::for('manual'));
        $this->assertSame('Roll call (A/L)', AttendanceSourceLabel::for('roll_call'));
    }
}
