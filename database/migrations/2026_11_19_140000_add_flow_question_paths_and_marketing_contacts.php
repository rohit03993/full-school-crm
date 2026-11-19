<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_inchat_form_fields', function (Blueprint $table) {
            $table->string('show_when', 160)->nullable()->after('choices');
        });

        Schema::create('whatsapp_marketing_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('mobile', 15);
            $table->json('extra')->nullable();
            $table->string('source', 40)->default('excel');
            $table->timestamps();

            $table->unique('mobile');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_marketing_contacts');

        Schema::table('whatsapp_inchat_form_fields', function (Blueprint $table) {
            $table->dropColumn('show_when');
        });
    }
};
