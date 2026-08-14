<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homework_assignments', function (Blueprint $table) {
            $table->foreignId('course_subject_id')
                ->nullable()
                ->after('batch_id')
                ->constrained('course_subjects')
                ->nullOnDelete();
            $table->foreignId('submitted_by_user_id')
                ->nullable()
                ->after('created_by_user_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('approved_by_user_id')
                ->nullable()
                ->after('submitted_by_user_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('status', 16)->default('sent')->after('content_type');
            $table->date('homework_date')->nullable()->after('description');
            $table->timestamp('submitted_at')->nullable()->after('published_at');
            $table->timestamp('approved_at')->nullable()->after('submitted_at');
            $table->timestamp('combined_sent_at')->nullable()->after('approved_at');

            $table->index(['batch_id', 'homework_date', 'status'], 'homework_batch_date_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('homework_assignments', function (Blueprint $table) {
            $table->dropIndex('homework_batch_date_status_idx');
            $table->dropConstrainedForeignId('course_subject_id');
            $table->dropConstrainedForeignId('submitted_by_user_id');
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropColumn([
                'status',
                'homework_date',
                'submitted_at',
                'approved_at',
                'combined_sent_at',
            ]);
        });
    }
};
