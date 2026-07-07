<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admissions', function (Blueprint $table): void {
            if (! Schema::hasColumn('admissions', 'planned_cash_amount')) {
                $table->decimal('planned_cash_amount', 12, 2)->nullable()->after('net_fee');
            }

            if (! Schema::hasColumn('admissions', 'planned_online_amount')) {
                $table->decimal('planned_online_amount', 12, 2)->nullable()->after('planned_cash_amount');
            }
        });

        Schema::table('fee_misc_charges', function (Blueprint $table): void {
            if (! Schema::hasColumn('fee_misc_charges', 'kind')) {
                $table->string('kind', 20)->default('bundled')->after('amount');
            }

            if (! Schema::hasColumn('fee_misc_charges', 'status')) {
                $table->string('status', 20)->default('bundled')->after('kind');
            }

            if (! Schema::hasColumn('fee_misc_charges', 'due_date')) {
                $table->date('due_date')->nullable()->after('status');
            }

            if (! Schema::hasColumn('fee_misc_charges', 'added_by_user_id')) {
                $table->foreignId('added_by_user_id')->nullable()->after('due_date')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('fee_misc_charges', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('added_by_user_id');
            }
        });

        Schema::table('fee_structures', function (Blueprint $table): void {
            if (! Schema::hasColumn('fee_structures', 'planned_cash_amount')) {
                $table->decimal('planned_cash_amount', 12, 2)->nullable()->after('pending_amount');
            }

            if (! Schema::hasColumn('fee_structures', 'planned_online_amount')) {
                $table->decimal('planned_online_amount', 12, 2)->nullable()->after('planned_cash_amount');
            }
        });

        Schema::table('payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('payments', 'fee_misc_charge_id')) {
                $table->foreignId('fee_misc_charge_id')->nullable()->after('fee_installment_id')->constrained('fee_misc_charges')->nullOnDelete();
            }

            if (! Schema::hasColumn('payments', 'tuition_amount')) {
                $table->decimal('tuition_amount', 12, 2)->nullable()->after('amount');
            }
        });

        if (Schema::hasColumn('fee_misc_charges', 'kind') && Schema::hasColumn('fee_misc_charges', 'status')) {
            DB::table('fee_misc_charges')
                ->whereNull('kind')
                ->orWhere('kind', '')
                ->update([
                    'kind' => 'bundled',
                    'status' => 'bundled',
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            if (Schema::hasColumn('payments', 'fee_misc_charge_id')) {
                $table->dropConstrainedForeignId('fee_misc_charge_id');
            }

            if (Schema::hasColumn('payments', 'tuition_amount')) {
                $table->dropColumn('tuition_amount');
            }
        });

        Schema::table('fee_structures', function (Blueprint $table): void {
            $columns = array_filter(
                ['planned_cash_amount', 'planned_online_amount'],
                fn (string $column): bool => Schema::hasColumn('fee_structures', $column),
            );

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('admissions', function (Blueprint $table): void {
            $columns = array_filter(
                ['planned_cash_amount', 'planned_online_amount'],
                fn (string $column): bool => Schema::hasColumn('admissions', $column),
            );

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('fee_misc_charges', function (Blueprint $table): void {
            if (Schema::hasColumn('fee_misc_charges', 'added_by_user_id')) {
                $table->dropConstrainedForeignId('added_by_user_id');
            }

            $columns = array_filter(
                ['kind', 'status', 'due_date', 'paid_at'],
                fn (string $column): bool => Schema::hasColumn('fee_misc_charges', $column),
            );

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
