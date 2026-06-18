<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_attendances', function (Blueprint $table) {
            $table->decimal('marks_obtained', 8, 2)->nullable()->after('is_present');
            $table->string('grade', 10)->nullable()->after('marks_obtained');
            $table->string('remarks')->nullable()->after('grade');
        });
    }

    public function down(): void
    {
        Schema::table('activity_attendances', function (Blueprint $table) {
            $table->dropColumn(['marks_obtained', 'grade', 'remarks']);
        });
    }
};
