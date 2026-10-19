<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_drops', function (Blueprint $table) {
            $table->string('reason_type', 40)->nullable()->after('reason');
            $table->foreignId('course_subject_id')->nullable()->after('reason_type')->constrained()->nullOnDelete();
            $table->string('subject_name')->nullable()->after('course_subject_id');
            $table->foreignId('subject_teacher_user_id')->nullable()->after('subject_name')->constrained('users')->nullOnDelete();
            $table->string('subject_teacher_name')->nullable()->after('subject_teacher_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('student_drops', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subject_teacher_user_id');
            $table->dropConstrainedForeignId('course_subject_id');
            $table->dropColumn(['reason_type', 'subject_name', 'subject_teacher_name']);
        });
    }
};
