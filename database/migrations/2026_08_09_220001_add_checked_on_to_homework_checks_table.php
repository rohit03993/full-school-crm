<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homework_checks', function (Blueprint $table) {
            $table->date('checked_on')->nullable()->after('topic');
            $table->index(['batch_id', 'course_subject_id', 'checked_on']);
        });

        DB::table('homework_checks')
            ->whereNull('checked_on')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('homework_checks')
                        ->where('id', $row->id)
                        ->update([
                            'checked_on' => substr((string) $row->created_at, 0, 10),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('homework_checks', function (Blueprint $table) {
            $table->dropIndex(['batch_id', 'course_subject_id', 'checked_on']);
            $table->dropColumn('checked_on');
        });
    }
};
