<?php

namespace Tests;

use App\Models\Setting;
use App\Services\CrmPermissionSyncService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Setting::flushValueCache();

        if (Schema::hasTable('permissions')) {
            app(CrmPermissionSyncService::class)->sync();
        }
    }
}
