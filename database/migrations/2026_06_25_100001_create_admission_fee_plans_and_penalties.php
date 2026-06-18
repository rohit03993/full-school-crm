<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->boolean('use_installment_plan')->default(false)->after('net_fee');
        });

        Schema::create('admission_misc_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->decimal('amount', 12, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['admission_id', 'sort_order']);
        });

        Schema::create('admission_installment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->decimal('amount', 12, 2);
            $table->date('due_date')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['admission_id', 'sort_order']);
        });

        Schema::create('fee_misc_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_structure_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->decimal('amount', 12, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['fee_structure_id', 'sort_order']);
        });

        Schema::create('fee_penalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_installment_id')->nullable()->constrained('fee_installments')->nullOnDelete();
            $table->string('penalty_type', 30);
            $table->date('penalty_date');
            $table->decimal('base_amount', 12, 2);
            $table->decimal('penalty_amount', 12, 2);
            $table->unsignedSmallInteger('days_late')->default(0);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('waived_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('waived_reason')->nullable();
            $table->timestamps();

            $table->index(['fee_structure_id', 'status']);
            $table->index(['fee_installment_id', 'penalty_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_penalties');
        Schema::dropIfExists('fee_misc_charges');
        Schema::dropIfExists('admission_installment_plans');
        Schema::dropIfExists('admission_misc_fees');

        Schema::table('admissions', function (Blueprint $table) {
            $table->dropColumn('use_installment_plan');
        });
    }
};
