<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_structure_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_structure_id')->constrained()->cascadeOnDelete();
            $table->decimal('old_course_fee', 12, 2);
            $table->decimal('new_course_fee', 12, 2);
            $table->decimal('old_discount', 12, 2);
            $table->decimal('new_discount', 12, 2);
            $table->decimal('old_net_fee', 12, 2);
            $table->decimal('new_net_fee', 12, 2);
            $table->foreignId('changed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['fee_structure_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_structure_history');
    }
};
