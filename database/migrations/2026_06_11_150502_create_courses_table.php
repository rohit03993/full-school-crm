<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('programme_category')->default('custom');
            $table->unsignedSmallInteger('duration');
            $table->string('duration_type');
            $table->decimal('fee', 10, 2)->default(0);
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index('status');
            $table->index('programme_category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
