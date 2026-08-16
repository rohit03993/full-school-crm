<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table): void {
                if (! Schema::hasColumn('payments', 'status')) {
                    $table->string('status')->default('active')->after('receipt_path');
                    $table->index('status');
                }

                if (! Schema::hasColumn('payments', 'allocation_snapshot')) {
                    $table->json('allocation_snapshot')->nullable()->after('shortfall_allocation');
                }

                if (! Schema::hasColumn('payments', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable()->after('status');
                }

                if (! Schema::hasColumn('payments', 'cancelled_by_user_id')) {
                    $table->foreignId('cancelled_by_user_id')
                        ->nullable()
                        ->after('cancelled_at')
                        ->constrained('users')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('payments', 'cancel_reason')) {
                    $table->text('cancel_reason')->nullable()->after('cancelled_by_user_id');
                }
            });
        }

        if (! Schema::hasTable('payment_cancellation_requests')) {
            Schema::create('payment_cancellation_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
                $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('reason');
                $table->string('status')->default('pending');
                $table->text('review_notes')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
                $table->index('payment_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_cancellation_requests');

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table): void {
                if (Schema::hasColumn('payments', 'cancelled_by_user_id')) {
                    $table->dropConstrainedForeignId('cancelled_by_user_id');
                }

                foreach (['cancel_reason', 'cancelled_at', 'status', 'allocation_snapshot'] as $column) {
                    if (Schema::hasColumn('payments', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
