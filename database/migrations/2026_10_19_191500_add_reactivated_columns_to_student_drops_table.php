<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_drops', function (Blueprint $table) {
            $table->timestamp('reactivated_at')->nullable()->after('dropped_at');
            $table->foreignId('reactivated_by_user_id')->nullable()->after('reactivated_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('student_drops', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reactivated_by_user_id');
            $table->dropColumn('reactivated_at');
        });
    }
};
