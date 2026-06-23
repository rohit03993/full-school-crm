<?php

namespace App\Services;

use App\Models\Enquiry;
use App\Models\Student;
use App\Support\DefaultCourse;
use App\Support\MeetingForOptions;
use Illuminate\Support\Collection;

class ConvertToAdmissionPresenter
{
    public function __construct(
        protected AdmissionService $admissions,
    ) {}

    /**
     * @return Collection<int, Enquiry>
     */
    public function convertibleEnquiries(Student $student): Collection
    {
        return $this->admissions->convertibleEnquiries($student);
    }

    public function formatEnquiryLabel(Enquiry $enquiry, bool $isLatest = false): string
    {
        $segments = array_filter([
            $enquiry->enquiry_number,
            $enquiry->course?->name ?? 'No course',
            $enquiry->lead_source?->label(),
            MeetingForOptions::label($enquiry->meeting_for),
            $enquiry->created_at?->format('d M Y'),
            $isLatest ? 'Latest' : null,
        ]);

        return implode(' · ', $segments);
    }

    /**
     * @param  Collection<int, Enquiry>  $convertible
     * @return array<int, string>
     */
    public function enquiryOptions(Collection $convertible): array
    {
        return $convertible
            ->values()
            ->mapWithKeys(fn (Enquiry $enquiry, int $index): array => [
                $enquiry->id => $this->formatEnquiryLabel($enquiry, $index === 0),
            ])
            ->all();
    }

    /**
     * @param  Collection<int, Enquiry>  $convertible
     */
    public function defaultEnquiryId(Collection $convertible): ?int
    {
        return $convertible->first()?->id;
    }

    /**
     * @param  Collection<int, Enquiry>  $convertible
     */
    public function selectionWarning(Collection $convertible): ?string
    {
        if ($convertible->count() < 2) {
            return null;
        }

        $latest = $convertible->first();

        if (! $this->isUndecidedCourse($latest)) {
            return null;
        }

        $olderWithCourse = $convertible
            ->slice(1)
            ->first(fn (Enquiry $enquiry): bool => ! $this->isUndecidedCourse($enquiry));

        if (! $olderWithCourse) {
            return null;
        }

        return 'Latest enquiry has no course yet — you must select a course below. Older option: '
            .$olderWithCourse->enquiry_number
            .' — '
            .($olderWithCourse->course?->name ?? 'course on file')
            .'.';
    }

    /**
     * @param  Collection<int, Enquiry>  $convertible
     */
    public function modalDescription(Collection $convertible): string
    {
        if ($convertible->count() === 1) {
            $enquiry = $convertible->first();

            return 'Select the course and confirm fees for: '.$this->formatEnquiryLabel($enquiry, true);
        }

        return 'Select the enquiry, course, and fees.';
    }

    public function enquiryNeedsCourseSelection(Enquiry $enquiry): bool
    {
        return $this->isUndecidedCourse($enquiry);
    }

    protected function isUndecidedCourse(Enquiry $enquiry): bool
    {
        return $enquiry->course?->code === DefaultCourse::UNDECIDED_CODE;
    }
}
