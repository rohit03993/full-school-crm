<?php

namespace App\Services;

use App\Models\Enquiry;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;

class StudentSearchService
{
    public const OUTCOME_FOUND = 'found';

    public const OUTCOME_MULTIPLE = 'multiple';

    public const OUTCOME_NOT_FOUND = 'not_found';

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
                ->with(['latestEnquiry.course'])
                ->where('mobile', $mobile)
                ->first();

            return $this->result(
                $student ? self::OUTCOME_FOUND : self::OUTCOME_NOT_FOUND,
                $student,
            );
        }

        if (filled($enquiryNumber)) {
            $enquiry = Enquiry::query()
                ->with(['student.latestEnquiry.course', 'course'])
                ->where('enquiry_number', $enquiryNumber)
                ->first();

            if ($enquiry?->student) {
                return $this->result(self::OUTCOME_FOUND, $enquiry->student);
            }

            return $this->result(self::OUTCOME_NOT_FOUND);
        }

        if (filled($enrollment)) {
            $enrollmentRecord = \App\Models\Enrollment::query()
                ->with(['student.latestEnquiry.course'])
                ->where('enrollment_number', strtoupper($enrollment))
                ->first();

            if ($enrollmentRecord?->student) {
                return $this->result(self::OUTCOME_FOUND, $enrollmentRecord->student);
            }

            return $this->result(self::OUTCOME_NOT_FOUND);
        }

        if (filled($name)) {
            $students = Student::query()
                ->with(['latestEnquiry.course'])
                ->where('name', 'like', '%'.$name.'%')
                ->orderBy('name')
                ->limit(25)
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
     * @return Collection<int, Enquiry>
     */
    public function recentEnquiries(int $limit = 5): Collection
    {
        return Enquiry::query()
            ->with(['student', 'course'])
            ->latest()
            ->limit($limit)
            ->get();
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
}
