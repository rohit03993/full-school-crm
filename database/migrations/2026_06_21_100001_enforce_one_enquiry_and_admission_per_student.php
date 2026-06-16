<?php

use App\Enums\AdmissionStatus;
use App\Models\Admission;
use App\Models\Enquiry;
use App\Models\Visit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->consolidateDuplicateAdmissions();
        $this->consolidateDuplicateEnquiries();

        if (! $this->uniqueIndexExists('enquiries', 'enquiries_student_id_unique')) {
            Schema::table('enquiries', function (Blueprint $table) {
                $table->unique('student_id');
            });
        }

        if (! $this->uniqueIndexExists('admissions', 'admissions_student_id_unique')) {
            Schema::table('admissions', function (Blueprint $table) {
                $table->unique('student_id');
            });
        }
    }

    public function down(): void
    {
        if ($this->uniqueIndexExists('admissions', 'admissions_student_id_unique')) {
            Schema::table('admissions', function (Blueprint $table) {
                $table->dropUnique(['student_id']);
            });
        }

        if ($this->uniqueIndexExists('enquiries', 'enquiries_student_id_unique')) {
            Schema::table('enquiries', function (Blueprint $table) {
                $table->dropUnique(['student_id']);
            });
        }
    }

    protected function consolidateDuplicateAdmissions(): void
    {
        $studentIds = DB::table('admissions')
            ->select('student_id')
            ->groupBy('student_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('student_id');

        foreach ($studentIds as $studentId) {
            $admissions = Admission::withTrashed()
                ->where('student_id', $studentId)
                ->with(['enrollment.feeStructure'])
                ->get();

            $keeper = $this->pickKeeperAdmission($admissions);

            $admissions
                ->reject(fn (Admission $admission): bool => $admission->id === $keeper->id)
                ->each(function (Admission $admission): void {
                    $enrollment = $admission->enrollment;

                    if ($enrollment) {
                        $enrollment->feeStructure?->forceDelete();
                        $enrollment->forceDelete();
                    }

                    $admission->documents()->get()->each->delete();
                    $admission->forceDelete();
                });
        }
    }

    protected function consolidateDuplicateEnquiries(): void
    {
        $studentIds = DB::table('enquiries')
            ->select('student_id')
            ->groupBy('student_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('student_id');

        foreach ($studentIds as $studentId) {
            $enquiries = Enquiry::withTrashed()
                ->where('student_id', $studentId)
                ->with(['admission' => fn ($query) => $query->withTrashed()])
                ->get();

            $keeper = $this->pickKeeperEnquiry($enquiries);

            $enquiries
                ->reject(fn (Enquiry $enquiry): bool => $enquiry->id === $keeper->id)
                ->each(function (Enquiry $enquiry): void {
                    Visit::query()
                        ->where('enquiry_id', $enquiry->id)
                        ->update(['enquiry_id' => null]);

                    $enquiry->forceDelete();
                });
        }
    }

    /**
     * @param  Collection<int, Admission>  $admissions
     */
    protected function pickKeeperAdmission(Collection $admissions): Admission
    {
        return $admissions
            ->sortByDesc(fn (Admission $admission): array => [
                $admission->deleted_at === null ? 1 : 0,
                $admission->enrollment?->is_active ? 1 : 0,
                match ($admission->status) {
                    AdmissionStatus::Approved => 4,
                    AdmissionStatus::VerificationPending => 3,
                    AdmissionStatus::Submitted => 2,
                    AdmissionStatus::Rejected => 1,
                    default => 0,
                },
                $admission->id,
            ])
            ->first();
    }

    /**
     * @param  Collection<int, Enquiry>  $enquiries
     */
    protected function pickKeeperEnquiry(Collection $enquiries): Enquiry
    {
        return $enquiries
            ->sortByDesc(fn (Enquiry $enquiry): array => [
                $enquiry->deleted_at === null ? 1 : 0,
                $enquiry->admission && $enquiry->admission->deleted_at === null ? 1 : 0,
                $enquiry->id,
            ])
            ->first();
    }

    protected function uniqueIndexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");

            foreach ($indexes as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        $indexes = DB::select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$indexName]);

        return count($indexes) > 0;
    }
};
