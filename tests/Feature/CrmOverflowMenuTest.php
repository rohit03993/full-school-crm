<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class CrmOverflowMenuTest extends TestCase
{
    public function test_overflow_menu_teleports_out_of_clipping_parents(): void
    {
        $level = ob_get_level();

        try {
            $html = Blade::render(
                '<x-crm.overflow-menu><x-slot name="trigger">Open</x-slot>Rename</x-crm.overflow-menu>',
            );

            $this->assertStringContainsString('x-teleport="body"', $html);
            $this->assertStringContainsString('crm-overflow-menu', $html);
            $this->assertStringContainsString('position:fixed', $html);
            $this->assertStringContainsString('this.place()', $html);
            $this->assertStringContainsString('Rename', $html);
            $this->assertStringContainsString('Open', $html);
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }
}
