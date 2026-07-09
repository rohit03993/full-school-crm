<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('activity_types')
            ->whereIn('slug', ['mock_test', 'workshop', 'event'])
            ->update(['is_enabled' => false]);
    }

    public function down(): void
    {
        DB::table('activity_types')
            ->whereIn('slug', ['mock_test', 'workshop', 'event'])
            ->update(['is_enabled' => true]);
    }
};
