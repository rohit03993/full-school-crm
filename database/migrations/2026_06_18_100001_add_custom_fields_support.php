<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('entity', 40);
            $table->string('field_key', 60);
            $table->string('label');
            $table->string('field_type', 20)->default('text');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['entity', 'field_key']);
            $table->index(['entity', 'is_active', 'sort_order']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->json('custom_data')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('custom_data');
        });

        Schema::dropIfExists('custom_field_definitions');
    }
};
