<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('practical_sessions', 'category')) {
            Schema::table('practical_sessions', function (Blueprint $table) {
                $table->string('title')->nullable()->after('id');
            });

            DB::table('practical_sessions')->update([
                'title' => DB::raw("CASE category
                    WHEN 'front_office' THEN 'Front Office'
                    WHEN 'food_production' THEN 'Food Production'
                    WHEN 'housekeeping' THEN 'Housekeeping'
                    WHEN 'fnb_service' THEN 'F&B Service'
                    ELSE 'Practical Session'
                END"),
            ]);

            Schema::table('practical_sessions', function (Blueprint $table) {
                $table->dropIndex(['category']);
            });

            Schema::table('practical_sessions', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }

        if (Schema::hasColumn('industrial_visits', 'location')) {
            Schema::table('industrial_visits', function (Blueprint $table) {
                $table->string('location')->nullable()->change();
            });
        }

        if (Schema::hasColumn('seminars', 'type')) {
            Schema::table('seminars', function (Blueprint $table) {
                $table->string('type')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('practical_sessions', 'category')) {
            Schema::table('practical_sessions', function (Blueprint $table) {
                $table->string('category')->nullable()->after('id');
            });

            Schema::table('practical_sessions', function (Blueprint $table) {
                $table->dropColumn('title');
                $table->index('category');
            });
        }

        Schema::table('industrial_visits', function (Blueprint $table) {
            $table->string('location')->nullable(false)->change();
        });

        Schema::table('seminars', function (Blueprint $table) {
            $table->string('type')->nullable(false)->change();
        });
    }
};
