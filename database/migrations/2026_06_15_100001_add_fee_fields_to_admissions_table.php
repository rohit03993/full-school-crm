<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->decimal('course_fee', 12, 2)->nullable()->after('graduation_percentage');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('course_fee');
            $table->decimal('net_fee', 12, 2)->nullable()->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->dropColumn(['course_fee', 'discount_amount', 'net_fee']);
        });
    }
};
