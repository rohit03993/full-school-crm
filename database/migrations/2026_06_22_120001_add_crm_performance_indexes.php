<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('meeting_with_user_id');
            $table->index('latest_visit_status');
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->index('next_follow_up_date');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->index(['is_call_blocked', 'next_call_followup_at'], 'students_call_followup_idx');
        });

        Schema::table('admissions', function (Blueprint $table) {
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['meeting_with_user_id']);
            $table->dropIndex(['latest_visit_status']);
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex(['next_follow_up_date']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('students_call_followup_idx');
        });

        Schema::table('admissions', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });
    }
};
