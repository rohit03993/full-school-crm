<?php

namespace App\Support;

/**
 * Marks a sales-demo install (e.g. demo.taskbook.co.in).
 * Does not change CRM behaviour — WhatsApp and features match a normal school install.
 * Use with SEED_DEMO_DATA for dummy XYZ School rows only on that server.
 */
class DemoMode
{
    public static function enabled(): bool
    {
        return filter_var(config('institute.demo_mode', false), FILTER_VALIDATE_BOOLEAN);
    }
}
