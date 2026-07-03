<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->string('campus_purpose', 32)->nullable()->after('status');
            $table->string('campus_outcome', 32)->nullable()->after('campus_purpose');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn(['campus_purpose', 'campus_outcome']);
        });
    }
};
