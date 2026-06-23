<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->timestamp('calling_assigned_at')->nullable()->after('meeting_with_user_id');
            $table->foreignId('calling_assigned_by_user_id')
                ->nullable()
                ->after('calling_assigned_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['meeting_with_user_id', 'calling_assigned_at'], 'enquiries_calling_assignment_index');
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropIndex('enquiries_calling_assignment_index');
            $table->dropConstrainedForeignId('calling_assigned_by_user_id');
            $table->dropColumn('calling_assigned_at');
        });
    }
};
