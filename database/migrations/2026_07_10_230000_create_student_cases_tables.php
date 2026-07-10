<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_cases', function (Blueprint $table): void {
            $table->id();
            $table->string('case_number')->unique();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('case_type');
            $table->string('status')->default('open');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->foreignId('opened_by_user_id')->constrained('users');
            $table->foreignId('current_assignee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('closing_note')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['current_assignee_user_id', 'status']);
        });

        Schema::create('student_case_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->constrained('users');
            $table->foreignId('assigned_by_user_id')->constrained('users');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('student_case_id');
        });

        Schema::table('student_calls', function (Blueprint $table): void {
            if (! Schema::hasColumn('student_calls', 'student_case_id')) {
                $table->foreignId('student_case_id')
                    ->nullable()
                    ->after('enquiry_id')
                    ->constrained('student_cases')
                    ->nullOnDelete();
            }
        });

        Schema::table('visits', function (Blueprint $table): void {
            if (! Schema::hasColumn('visits', 'student_case_id')) {
                $table->foreignId('student_case_id')
                    ->nullable()
                    ->after('enquiry_id')
                    ->constrained('student_cases')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table): void {
            if (Schema::hasColumn('visits', 'student_case_id')) {
                $table->dropConstrainedForeignId('student_case_id');
            }
        });

        Schema::table('student_calls', function (Blueprint $table): void {
            if (Schema::hasColumn('student_calls', 'student_case_id')) {
                $table->dropConstrainedForeignId('student_case_id');
            }
        });

        Schema::dropIfExists('student_case_assignments');
        Schema::dropIfExists('student_cases');
    }
};
