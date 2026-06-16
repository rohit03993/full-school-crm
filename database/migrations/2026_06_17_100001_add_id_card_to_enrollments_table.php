<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->string('id_card_path')->nullable()->after('is_active');
            $table->timestamp('id_card_generated_at')->nullable()->after('id_card_path');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn(['id_card_path', 'id_card_generated_at']);
        });
    }
};
