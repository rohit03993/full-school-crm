<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('serial_number', 64)->unique();
            $table->string('location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('attlog_stamp', 64)->nullable();
            $table->string('operlog_stamp', 64)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_punch_at')->nullable();
            $table->unsignedInteger('today_punch_count')->default(0);
            $table->date('today_punch_count_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('biometric_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biometric_device_id')->nullable()->constrained('biometric_devices')->nullOnDelete();
            $table->string('serial_number', 64)->index();
            $table->string('user_pin', 64)->index();
            $table->dateTime('punched_at');
            $table->unsignedTinyInteger('punch_status')->nullable();
            $table->unsignedTinyInteger('verify_type')->nullable();
            $table->string('work_code', 32)->nullable();
            $table->unsignedBigInteger('punch_log_id')->nullable()->index();
            $table->string('process_status', 32)->default('pending')->index();
            $table->text('process_error')->nullable();
            $table->longText('raw_line')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(
                ['serial_number', 'user_pin', 'punched_at'],
                'biometric_punches_dedupe_unique',
            );
            $table->index(['punched_at']);
        });

        if (! Schema::hasTable('punch_logs')) {
            Schema::create('punch_logs', function (Blueprint $table) {
                $table->id();
                $table->string('employee_id', 64);
                $table->date('punch_date');
                $table->time('punch_time');
                $table->string('device_name')->nullable();
                $table->string('area_name')->nullable();
                $table->boolean('is_manual')->default(false);
                $table->timestamps();

                $table->index(['employee_id', 'punch_date'], 'punch_logs_employee_date_idx');
                $table->index(['punch_date', 'punch_time'], 'punch_logs_date_time_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_punches');
        Schema::dropIfExists('biometric_devices');
        // Do not drop punch_logs — may be owned by EasyTimePro / shared DB.
    }
};
