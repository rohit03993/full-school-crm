<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batch_staff_assignments', function (Blueprint $table) {
            $table->dropUnique(['batch_id', 'course_subject_id']);
            $table->unique(
                ['batch_id', 'course_subject_id', 'user_id'],
                'batch_staff_subject_teacher_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('batch_staff_assignments', function (Blueprint $table) {
            $table->dropUnique('batch_staff_subject_teacher_unique');
            $table->unique(['batch_id', 'course_subject_id']);
        });
    }
};
