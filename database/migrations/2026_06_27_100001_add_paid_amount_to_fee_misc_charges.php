<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_misc_charges', function (Blueprint $table): void {
            if (! Schema::hasColumn('fee_misc_charges', 'paid_amount')) {
                $table->decimal('paid_amount', 12, 2)->default(0)->after('amount');
            }
        });

        if (Schema::hasColumn('fee_misc_charges', 'paid_amount') && Schema::hasColumn('fee_misc_charges', 'status')) {
            DB::table('fee_misc_charges')
                ->where('status', 'paid')
                ->update([
                    'paid_amount' => DB::raw('amount'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('fee_misc_charges', function (Blueprint $table): void {
            if (Schema::hasColumn('fee_misc_charges', 'paid_amount')) {
                $table->dropColumn('paid_amount');
            }
        });
    }
};
