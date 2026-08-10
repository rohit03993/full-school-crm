<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_calls', function (Blueprint $table): void {
            $table->string('call_purpose')->nullable()->after('tags');
        });
    }

    public function down(): void
    {
        Schema::table('student_calls', function (Blueprint $table): void {
            $table->dropColumn('call_purpose');
        });
    }
};
