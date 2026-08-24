<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_reminder_logs', function (Blueprint $table) {
            $table->string('stage', 32)->default('overdue')->after('fee_installment_id');
            $table->index(['fee_installment_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::table('fee_reminder_logs', function (Blueprint $table) {
            $table->dropIndex(['fee_installment_id', 'stage']);
            $table->dropColumn('stage');
        });
    }
};
