<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_structure_history', function (Blueprint $table) {
            $table->json('schedule_changes')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('fee_structure_history', function (Blueprint $table) {
            $table->dropColumn('schedule_changes');
        });
    }
};
