<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homework_subject_closures', function (Blueprint $table) {
            $table->boolean('whole_subject')->default(false)->after('teacher_user_id');
        });

        // Reasons saved before this change answered for every teacher of the subject.
        DB::table('homework_subject_closures')->update(['whole_subject' => true]);

        // Add the new rule first: MySQL needs an index starting with batch_id for its foreign key.
        Schema::table('homework_subject_closures', function (Blueprint $table) {
            $table->unique(
                ['batch_id', 'course_subject_id', 'homework_date', 'teacher_user_id'],
                'homework_subject_closures_teacher_unique',
            );
        });

        Schema::table('homework_subject_closures', function (Blueprint $table) {
            $table->dropUnique('homework_subject_closures_unique');
        });
    }

    public function down(): void
    {
        Schema::table('homework_subject_closures', function (Blueprint $table) {
            $table->unique(
                ['batch_id', 'course_subject_id', 'homework_date'],
                'homework_subject_closures_unique',
            );
        });

        Schema::table('homework_subject_closures', function (Blueprint $table) {
            $table->dropUnique('homework_subject_closures_teacher_unique');
            $table->dropColumn('whole_subject');
        });
    }
};
