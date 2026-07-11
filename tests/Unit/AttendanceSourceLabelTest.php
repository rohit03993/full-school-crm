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
        $this->assertSame('Manually marked', AttendanceSourceLabel::for('manual'));
        $this->assertSame('Manually marked (Priya Sharma)', AttendanceSourceLabel::for('manual', 'Priya Sharma'));
        $this->assertSame('Roll call (A/L)', AttendanceSourceLabel::for('roll_call'));
        $this->assertSame('Roll call (Priya Sharma)', AttendanceSourceLabel::for('roll_call', 'Priya Sharma'));
    }

    #[Test]
    public function it_formats_manual_marked_labels(): void
    {
        $this->assertSame('Manually marked', AttendanceSourceLabel::manualMarked());
        $this->assertSame('Manually marked (Admin)', AttendanceSourceLabel::manualMarked('Admin'));
        $this->assertTrue(AttendanceSourceLabel::isManual('manual'));
        $this->assertFalse(AttendanceSourceLabel::isManual('biometric'));
    }
}
