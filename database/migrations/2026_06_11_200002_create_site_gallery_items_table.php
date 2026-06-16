<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_gallery_items', function (Blueprint $table) {
            $table->id();
            $table->string('image_path');
            $table->string('alt');
            $table->string('caption');
            $table->string('span_class')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_gallery_items');
    }
};
