<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_conduct_records', function (Blueprint $table) {
            $table->string('parent_notice_status', 20)->nullable()->after('recorded_by_user_id');
            $table->timestamp('parent_notice_sent_at')->nullable()->after('parent_notice_status');
            $table->foreignId('parent_notice_by_user_id')->nullable()->after('parent_notice_sent_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('student_conduct_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_notice_by_user_id');
            $table->dropColumn(['parent_notice_status', 'parent_notice_sent_at']);
        });
    }
};
