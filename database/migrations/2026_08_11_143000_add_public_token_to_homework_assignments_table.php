<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homework_assignments', function (Blueprint $table) {
            $table->string('public_token', 64)->nullable()->unique()->after('file_path');
        });

        DB::table('homework_assignments')
            ->whereNull('public_token')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('homework_assignments')
                        ->where('id', $row->id)
                        ->update(['public_token' => Str::random(48)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('homework_assignments', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropColumn('public_token');
        });
    }
};
