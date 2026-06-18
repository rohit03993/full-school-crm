<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_installment_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->decimal('percentage', 5, 2);
            $table->unsignedSmallInteger('due_days_after_enrollment')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['course_id', 'sort_order']);
        });

        Schema::create('fee_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_structure_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->decimal('amount', 12, 2);
            $table->date('due_date')->nullable();
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('pending_amount', 12, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['fee_structure_id', 'sort_order']);
            $table->index(['due_date', 'pending_amount']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('fee_installment_id')
                ->nullable()
                ->after('fee_structure_id')
                ->constrained('fee_installments')
                ->nullOnDelete();
        });

        foreach (DB::table('fee_structures')->get() as $feeStructure) {
            if (DB::table('fee_installments')->where('fee_structure_id', $feeStructure->id)->exists()) {
                continue;
            }

            DB::table('fee_installments')->insert([
                'fee_structure_id' => $feeStructure->id,
                'label' => 'Full fee',
                'amount' => $feeStructure->net_fee,
                'due_date' => null,
                'paid_amount' => $feeStructure->paid_amount,
                'pending_amount' => $feeStructure->pending_amount,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fee_installment_id');
        });

        Schema::dropIfExists('fee_installments');
        Schema::dropIfExists('course_installment_templates');
    }
};
