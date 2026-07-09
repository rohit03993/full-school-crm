<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fee_misc_charge_adjustment_requests')) {
            return;
        }

        Schema::table('fee_misc_charge_adjustment_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('fee_misc_charge_adjustment_requests', 'applied_amount')) {
                $table->decimal('applied_amount', 12, 2)->nullable()->after('discount_amount');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fee_misc_charge_adjustment_requests')) {
            return;
        }

        Schema::table('fee_misc_charge_adjustment_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('fee_misc_charge_adjustment_requests', 'applied_amount')) {
                $table->dropColumn('applied_amount');
            }
        });
    }
};
