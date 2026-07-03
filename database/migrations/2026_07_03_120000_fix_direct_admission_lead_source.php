<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('enquiries') || ! Schema::hasTable('visits')) {
            return;
        }

        $enquiryIds = DB::table('visits')
            ->where('remarks', 'Added from Students → Add Student')
            ->pluck('enquiry_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($enquiryIds === []) {
            return;
        }

        DB::table('enquiries')
            ->whereIn('id', $enquiryIds)
            ->where('lead_source', 'walk_in')
            ->update(['lead_source' => 'direct_admission']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('enquiries') || ! Schema::hasTable('visits')) {
            return;
        }

        $enquiryIds = DB::table('visits')
            ->where('remarks', 'Added from Students → Add Student')
            ->pluck('enquiry_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($enquiryIds === []) {
            return;
        }

        DB::table('enquiries')
            ->whereIn('id', $enquiryIds)
            ->where('lead_source', 'direct_admission')
            ->update(['lead_source' => 'walk_in']);
    }
};
