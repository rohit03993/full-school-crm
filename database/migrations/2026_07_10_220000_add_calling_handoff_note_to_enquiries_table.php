<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table): void {
            if (! Schema::hasColumn('enquiries', 'calling_handoff_note')) {
                $table->text('calling_handoff_note')->nullable()->after('calling_assigned_by_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table): void {
            if (Schema::hasColumn('enquiries', 'calling_handoff_note')) {
                $table->dropColumn('calling_handoff_note');
            }
        });
    }
};
