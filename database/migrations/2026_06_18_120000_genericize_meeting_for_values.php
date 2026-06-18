<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('enquiries')) {
            return;
        }

        DB::table('enquiries')->where('meeting_for', 'folks_india')->update(['meeting_for' => 'school']);
        DB::table('enquiries')->where('meeting_for', 'english_coffee')->update(['meeting_for' => 'coaching']);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE enquiries MODIFY meeting_for VARCHAR(255) NOT NULL DEFAULT 'school'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('enquiries')) {
            return;
        }

        DB::table('enquiries')->where('meeting_for', 'school')->update(['meeting_for' => 'folks_india']);
        DB::table('enquiries')->where('meeting_for', 'coaching')->update(['meeting_for' => 'english_coffee']);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE enquiries MODIFY meeting_for VARCHAR(255) NOT NULL DEFAULT 'folks_india'");
        }
    }
};
