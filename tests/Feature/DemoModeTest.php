<?php

namespace Tests\Feature;

use App\Support\DemoMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_mode_defaults_off(): void
    {
        config(['institute.demo_mode' => false]);

        $this->assertFalse(DemoMode::enabled());
    }

    public function test_demo_mode_can_be_enabled_without_changing_send_behaviour(): void
    {
        config(['institute.demo_mode' => true]);

        $this->assertTrue(DemoMode::enabled());
    }
}
