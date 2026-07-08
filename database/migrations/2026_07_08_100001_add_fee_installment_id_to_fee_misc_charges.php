<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_misc_charges', function (Blueprint $table): void {
            if (! Schema::hasColumn('fee_misc_charges', 'fee_installment_id')) {
                $table->foreignId('fee_installment_id')
                    ->nullable()
                    ->after('fee_structure_id')
                    ->constrained('fee_installments')
                    ->nullOnDelete();

                $table->index(['fee_installment_id', 'kind']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('fee_misc_charges', function (Blueprint $table): void {
            if (Schema::hasColumn('fee_misc_charges', 'fee_installment_id')) {
                $table->dropConstrainedForeignId('fee_installment_id');
            }
        });
    }
};
