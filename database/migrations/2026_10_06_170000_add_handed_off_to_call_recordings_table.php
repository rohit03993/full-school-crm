<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('call_recordings') && ! Schema::hasColumn('call_recordings', 'handed_off')) {
            Schema::table('call_recordings', function (Blueprint $table) {
                $table->boolean('handed_off')->default(false)->after('processing_status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('call_recordings') && Schema::hasColumn('call_recordings', 'handed_off')) {
            Schema::table('call_recordings', function (Blueprint $table) {
                $table->dropColumn('handed_off');
            });
        }
    }
};
