<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('face_verification_requests', function (Blueprint $table) {
            $table->string('subject', 16)->default('student')->after('student_id');
            $table->foreignId('user_id')->nullable()->after('subject')->constrained('users')->nullOnDelete();
            $table->index(['subject', 'enrollment_number']);
        });
    }

    public function down(): void
    {
        Schema::table('face_verification_requests', function (Blueprint $table) {
            $table->dropIndex(['subject', 'enrollment_number']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('subject');
        });
    }
};
