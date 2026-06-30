<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_manual_punches', function (Blueprint $table) {
            $table->id();
            $table->string('enrollment_number', 64);
            $table->date('punch_date');
            $table->time('punch_time');
            $table->string('state', 8);
            $table->foreignId('marked_by_user_id')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['enrollment_number', 'punch_date']);
        });

        Schema::create('attendance_punch_whatsapp_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('enrollment_number', 64);
            $table->string('state', 8);
            $table->date('punch_date');
            $table->time('punch_time');
            $table->string('phone', 20)->nullable();
            $table->string('status', 32);
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['enrollment_number', 'punch_date', 'punch_time', 'state', 'phone'],
                'attendance_punch_wa_unique',
            );
        });

        $table = (string) config('attendance.punch_table', 'punch_logs');

        if (Schema::hasTable($table)) {
            $this->addPunchLogIndexes($table);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_punch_whatsapp_logs');
        Schema::dropIfExists('attendance_manual_punches');
    }

    private function addPunchLogIndexes(string $table): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $indexes = collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->pluck('Key_name')
            ->unique();

        if (! $indexes->contains('punch_logs_employee_date_idx')) {
            DB::statement("ALTER TABLE `{$table}` ADD INDEX punch_logs_employee_date_idx (employee_id, punch_date)");
        }

        if (! $indexes->contains('punch_logs_date_time_idx')) {
            DB::statement("ALTER TABLE `{$table}` ADD INDEX punch_logs_date_time_idx (punch_date, punch_time)");
        }
    }
};
