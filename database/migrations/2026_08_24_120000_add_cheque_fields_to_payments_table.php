<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('cheque_number', 6)->nullable()->after('utr_number');
            $table->date('cheque_date')->nullable()->after('cheque_number');
            $table->string('cheque_bank_name', 100)->nullable()->after('cheque_date');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['cheque_number', 'cheque_date', 'cheque_bank_name']);
        });
    }
};
