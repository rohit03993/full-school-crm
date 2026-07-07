<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_whatsapp_messages', function (Blueprint $table) {
            $table->string('message_type', 32)->default('text')->after('body_preview');
            $table->string('media_id')->nullable()->after('message_type');
            $table->string('media_path')->nullable()->after('media_id');
            $table->string('media_mime_type')->nullable()->after('media_path');
            $table->string('media_filename')->nullable()->after('media_mime_type');
            $table->text('caption')->nullable()->after('media_filename');
        });
    }

    public function down(): void
    {
        Schema::table('meta_whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn([
                'message_type',
                'media_id',
                'media_path',
                'media_mime_type',
                'media_filename',
                'caption',
            ]);
        });
    }
};
