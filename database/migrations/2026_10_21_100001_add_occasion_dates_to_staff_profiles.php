<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->after('mobile');
            $table->date('marriage_anniversary')->nullable()->after('date_of_birth');
        });
    }

    public function down(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->dropColumn(['date_of_birth', 'marriage_anniversary']);
        });
    }
};
