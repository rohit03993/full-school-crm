<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            if (! Schema::hasColumn('biometric_devices', 'requires_face_verify')) {
                $table->boolean('requires_face_verify')->default(false)->after('is_active');
            }

            if (! Schema::hasColumn('biometric_devices', 'face_verify_device_id')) {
                $table->uuid('face_verify_device_id')->nullable()->after('requires_face_verify');
            }
        });
    }

    public function down(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            if (Schema::hasColumn('biometric_devices', 'face_verify_device_id')) {
                $table->dropColumn('face_verify_device_id');
            }

            if (Schema::hasColumn('biometric_devices', 'requires_face_verify')) {
                $table->dropColumn('requires_face_verify');
            }
        });
    }
};
