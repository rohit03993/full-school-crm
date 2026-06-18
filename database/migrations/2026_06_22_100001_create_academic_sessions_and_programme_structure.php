<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_current')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_current', 'is_active']);
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->foreignId('academic_session_id')
                ->nullable()
                ->after('course_id')
                ->constrained()
                ->nullOnDelete();
            $table->string('section')->nullable()->after('name');
            $table->string('shift')->nullable()->after('section');
        });

        if (Schema::hasColumn('courses', 'course_type') && ! Schema::hasColumn('courses', 'programme_category')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->string('programme_category')->default('custom')->after('code');
            });

            DB::table('courses')->where('course_type', 'bsc')->update(['programme_category' => 'school']);
            DB::table('courses')->where('course_type', 'diploma')->update(['programme_category' => 'coaching']);
            DB::table('courses')->where('course_type', 'certificate')->update(['programme_category' => 'certificate']);
            DB::table('courses')->where('course_type', 'custom')->update(['programme_category' => 'custom']);

            Schema::table('courses', function (Blueprint $table) {
                $table->dropColumn('course_type');
            });

            Schema::table('courses', function (Blueprint $table) {
                $table->index('programme_category');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('courses', 'programme_category') && ! Schema::hasColumn('courses', 'course_type')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->string('course_type')->default('custom')->after('code');
            });

            DB::table('courses')->where('programme_category', 'school')->update(['course_type' => 'bsc']);
            DB::table('courses')->whereIn('programme_category', ['coaching', 'college', 'certificate'])
                ->update(['course_type' => 'diploma']);
            DB::table('courses')->where('programme_category', 'custom')->update(['course_type' => 'custom']);

            Schema::table('courses', function (Blueprint $table) {
                $table->dropIndex(['programme_category']);
                $table->dropColumn('programme_category');
            });

            Schema::table('courses', function (Blueprint $table) {
                $table->index('course_type');
            });
        }

        Schema::table('batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_session_id');
            $table->dropColumn(['section', 'shift']);
        });

        Schema::dropIfExists('academic_sessions');
    }
};
