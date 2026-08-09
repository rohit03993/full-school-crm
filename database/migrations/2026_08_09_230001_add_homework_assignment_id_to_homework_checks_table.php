<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homework_checks', function (Blueprint $table) {
            $table->foreignId('homework_assignment_id')
                ->nullable()
                ->after('course_subject_id')
                ->constrained('homework_assignments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('homework_checks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('homework_assignment_id');
        });
    }
};
