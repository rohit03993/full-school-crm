<?php

namespace App\Services;

use App\Enums\StudentStatus;
use App\Models\Enquiry;
use App\Models\Student;
use App\Support\CrmPagination;
use Illuminate\Database\Eloquent\Collection;

class StudentSearchService
{
    public const OUTCOME_FOUND = 'found';

    public const OUTCOME_MULTIPLE = 'multiple';

    public const OUTCOME_NOT_FOUND = 'not_found';

    /**
     * @return array<int|string, mixed>
     */
    protected function searchRelations(): array
    {
        return [
            'latestEnquiry.course',
            'lastCall.staff',
            'activeEnrollment.admission.documents',
            'activeBatchStudent.batch',
            'admissions' => fn ($query) => $query->latest()->limit(1)->with('documents'),
        ];
    }

    /**
     * @return array{outcome: string, student: ?Student, students: Collection<int, Student>}
     */
    public function search(
        ?string $mobile,
        ?string $name,
        ?string $enrollment = null,
        ?string $enquiryNumber = null,
    ): array {
        $mobile = $this->digitsOnly($mobile);
        $name = filled($name) ? trim($name) : null;
        $enrollment = filled($enrollment) ? trim($enrollment) : null;
        $enquiryNumber = filled($enquiryNumber) ? strtoupper(trim($enquiryNumber)) : null;

        if (filled($mobile)) {
            $student = Student::query()
                ->with($this->searchRelations())
                ->where(function ($query) use ($mobile): void {
                    $query->where('mobile', $mobile)
                        ->orWhere('alternate_mobile', $mobile);
                })
                ->first();

            return $this->result(
                $student ? self::OUTCOME_FOUND : self::OUTCOME_NOT_FOUND,
                $student,
            );
        }

        if (filled($enquiryNumber)) {
            $enquiry = Enquiry::query()
                ->with(['student' => fn ($query) => $query->with($this->searchRelations()), 'course'])
                ->where('enquiry_number', $enquiryNumber)
                ->first();

            if ($enquiry?->student) {
                return $this->result(self::OUTCOME_FOUND, $enquiry->student);
            }

            return $this->result(self::OUTCOME_NOT_FOUND);
        }

        if (filled($enrollment)) {
            $enrollmentRecord = \App\Models\Enrollment::query()
                ->with(['student' => fn ($query) => $query->with($this->searchRelations())])
                ->where('enrollment_number', strtoupper($enrollment))
                ->first();

            if ($enrollmentRecord?->student) {
                return $this->result(self::OUTCOME_FOUND, $enrollmentRecord->student);
            }

            return $this->result(self::OUTCOME_NOT_FOUND);
        }

        if (filled($name)) {
            $students = Student::query()
                ->with($this->searchRelations())
                ->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($name).'%'])
                ->orderBy('name')
                ->orderByDesc('updated_at')
                ->limit(CrmPagination::PER_PAGE)
                ->get();

            if ($students->isEmpty()) {
                return $this->result(self::OUTCOME_NOT_FOUND);
            }

            if ($students->count() === 1) {
                return $this->result(self::OUTCOME_FOUND, $students->first());
            }

            return $this->result(self::OUTCOME_MULTIPLE, students: $students);
        }

        return $this->result(self::OUTCOME_NOT_FOUND);
    }

    /**
     * @param  Collection<int, Student>|null  $students
     * @return array{outcome: string, student: ?Student, students: Collection<int, Student>}
     */
    protected function result(string $outcome, ?Student $student = null, ?Collection $students = null): array
    {
        return [
            'outcome' => $outcome,
            'student' => $student,
            'students' => $students ?? new Collection,
        ];
    }

    protected function digitsOnly(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);

        return filled($digits) ? $digits : null;
    }

    /**
     * Top-bar lookup: name, mobile, or roll. Returns leads and students together.
     *
     * @return Collection<int, Student>
     */
    public function quickSearch(string $term, int $limit = 8): Collection
    {
        $term = trim(preg_replace('/\s+/u', ' ', $term) ?? '');

        if ($term === '') {
            return new Collection;
        }

        $like = '%'.$this->escapeLike(mb_strtolower($term)).'%';
        $digits = $this->digitsOnly($term);
        $roll = $this->escapeLike(strtoupper($term));
        $words = array_values(array_filter(
            preg_split('/\s+/u', $term) ?: [],
            fn (string $word): bool => mb_strlen($word) >= 2,
        ));

        return Student::query()
            ->with(['activeEnrollment', 'latestEnquiry'])
            ->where(function ($query) use ($like, $digits, $roll, $words): void {
                $query->whereRaw('LOWER(name) LIKE ?', [$like]);

                foreach ($words as $word) {
                    $query->orWhereRaw('LOWER(name) LIKE ?', ['%'.$this->escapeLike(mb_strtolower($word)).'%']);
                }

                $query->orWhereHas(
                    'enrollments',
                    fn ($enrollment) => $enrollment->where('enrollment_number', 'like', '%'.$roll.'%'),
                );

                if (filled($digits) && strlen($digits) >= 4) {
                    $query->orWhere('mobile', 'like', '%'.$digits.'%')
                        ->orWhere('alternate_mobile', 'like', '%'.$digits.'%');
                }
            })
            ->orderByRaw("CASE
                WHEN LOWER(name) LIKE ? THEN 0
                WHEN status = ? THEN 1
                WHEN status = ? THEN 2
                ELSE 3
            END", [$like, StudentStatus::Enrolled->value, StudentStatus::Enquiry->value])
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    protected function escapeLike(string $value): string
    {
        return str_replace(['%', '_'], ['\\%', '\\_'], $value);
    }
}
