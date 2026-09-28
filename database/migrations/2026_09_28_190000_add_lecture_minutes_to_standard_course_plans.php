<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('standard_course_plans', function (Blueprint $table) {
            $table->unsignedSmallInteger('lecture_minutes')->default(60)->after('course_subject_id');
        });
    }

    public function down(): void
    {
        Schema::table('standard_course_plans', function (Blueprint $table) {
            $table->dropColumn('lecture_minutes');
        });
    }
};
