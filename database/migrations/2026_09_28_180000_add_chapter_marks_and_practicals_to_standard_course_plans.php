<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('standard_course_chapters', function (Blueprint $table) {
            $table->unsignedSmallInteger('estimated_marks')->nullable()->after('name');
        });

        Schema::create('standard_course_practicals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standard_course_plan_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind', 20)->default('experiment');
            $table->unsignedInteger('planned_minutes')->nullable();
            $table->unsignedSmallInteger('estimated_marks')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standard_course_practicals');

        Schema::table('standard_course_chapters', function (Blueprint $table) {
            $table->dropColumn('estimated_marks');
        });
    }
};
