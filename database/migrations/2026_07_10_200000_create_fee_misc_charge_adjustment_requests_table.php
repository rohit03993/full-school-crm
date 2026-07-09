<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fee_misc_charge_adjustment_requests')) {
            return;
        }

        Schema::create('fee_misc_charge_adjustment_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fee_misc_charge_id')->constrained('fee_misc_charges')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->decimal('discount_amount', 12, 2)->nullable();
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('fee_misc_charge_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_misc_charge_adjustment_requests');
    }
};
