<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_calls', function (Blueprint $table) {
            $table->unsignedTinyInteger('duration_seconds')->nullable()->after('duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('student_calls', function (Blueprint $table) {
            $table->dropColumn('duration_seconds');
        });
    }
};
