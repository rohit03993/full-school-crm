<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_import_batches')) {
            return;
        }

        Schema::table('student_import_batches', function (Blueprint $table) {
            $table->dropForeign(['academic_session_id']);
            $table->dropForeign(['course_id']);
        });

        Schema::table('student_import_batches', function (Blueprint $table) {
            $table->foreignId('academic_session_id')->nullable()->change();
            $table->foreignId('course_id')->nullable()->change();
        });

        Schema::table('student_import_batches', function (Blueprint $table) {
            $table->foreign('academic_session_id')
                ->references('id')
                ->on('academic_sessions')
                ->restrictOnDelete();
            $table->foreign('course_id')
                ->references('id')
                ->on('courses')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('student_import_batches')) {
            return;
        }

        Schema::table('student_import_batches', function (Blueprint $table) {
            $table->dropForeign(['academic_session_id']);
            $table->dropForeign(['course_id']);
        });

        Schema::table('student_import_batches', function (Blueprint $table) {
            $table->foreignId('academic_session_id')->nullable(false)->change();
            $table->foreignId('course_id')->nullable(false)->change();
        });

        Schema::table('student_import_batches', function (Blueprint $table) {
            $table->foreign('academic_session_id')
                ->references('id')
                ->on('academic_sessions')
                ->restrictOnDelete();
            $table->foreign('course_id')
                ->references('id')
                ->on('courses')
                ->restrictOnDelete();
        });
    }
};
