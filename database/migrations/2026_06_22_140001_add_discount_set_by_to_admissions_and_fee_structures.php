<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->foreignId('discount_set_by_user_id')
                ->nullable()
                ->after('discount_amount')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::table('fee_structures', function (Blueprint $table) {
            $table->foreignId('discount_set_by_user_id')
                ->nullable()
                ->after('discount_amount')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fee_structures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_set_by_user_id');
        });

        Schema::table('admissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_set_by_user_id');
        });
    }
};
