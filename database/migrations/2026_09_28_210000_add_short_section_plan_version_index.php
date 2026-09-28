<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('section_course_plan_versions')) {
            return;
        }

        foreach (Schema::getIndexes('section_course_plan_versions') as $index) {
            if (($index['unique'] ?? false) && ($index['columns'] ?? []) === ['section_course_plan_id', 'version']) {
                return;
            }
        }

        Schema::table('section_course_plan_versions', function (Blueprint $table) {
            $table->unique(['section_course_plan_id', 'version'], 'section_plan_version_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('section_course_plan_versions')) {
            return;
        }

        foreach (Schema::getIndexes('section_course_plan_versions') as $index) {
            if (($index['name'] ?? '') !== 'section_plan_version_unique') {
                continue;
            }

            Schema::table('section_course_plan_versions', function (Blueprint $table) {
                $table->dropUnique('section_plan_version_unique');
            });

            return;
        }
    }
};
