<?php

namespace App\Services;

use App\Models\Enquiry;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Support\InstituteSettings;
use Illuminate\Support\Carbon;

class WhatsAppTemplateParamResolver
{
    /**
     * @return array<string, string>
     */
    public static function sourceOptions(): array
    {
        return [
            'student.name' => 'Student name',
            'student.father_name' => 'Father name',
            'student.mobile' => 'Student mobile',
            'student.enrollment_number' => 'Roll No.',
            'batch.name' => 'Batch name',
            'enrollment.course.name' => 'Course (active enrollment)',
            'course.name' => 'Course (latest enquiry)',
            'institute.name' => 'Institute name',
            'institute.phone' => 'Institute phone',
            'caller.name' => 'Caller / staff name',
            'caller.mobile' => 'Caller mobile',
            'campaign.topic' => 'Announcement topic',
            'campaign.subject' => 'Announcement subject',
            'campaign.date' => 'Announcement date',
            'campaign.time' => 'Announcement time',
            'activity.test_name' => 'Test / exam name',
            'activity.test_date' => 'Test date',
            'activity.marks_summary' => 'All subject marks (combined)',
            'attendance.date' => 'Attendance date',
            'attendance.time' => 'Attendance check-in time',
            'attendance.status' => 'Attendance status (per student)',
        ];
    }

    public function resolve(
        string $source,
        Student $student,
        ?User $sender = null,
        ?Enquiry $enquiry = null,
        ?WhatsAppCampaign $campaign = null,
    ): string {
        if (str_starts_with($source, '"') && str_ends_with($source, '"')) {
            return trim($source, '"');
        }

        $enquiry ??= $student->enquiries()->with('course')->latest()->first();
        $institute = InstituteSettings::forDocuments();

        return match ($source) {
            'student.name' => (string) ($student->name ?? ''),
            'student.father_name' => (string) ($student->father_name ?? ''),
            'student.mobile' => (string) ($student->mobile ?? ''),
            'student.enrollment_number' => $this->resolveEnrollmentNumber($student, $campaign),
            'batch.name' => (string) (
                $student->activeBatchStudent?->batch?->name
                ?? $campaign?->batch?->name
                ?? ''
            ),
            'enrollment.course.name' => (string) (
                $student->activeEnrollment?->course?->name
                ?? $campaign?->course?->name
                ?? ''
            ),
            'course.name' => (string) ($enquiry?->course?->name ?? $campaign?->course?->name ?? ''),
            'institute.name' => (string) ($institute['name'] ?? ''),
            'institute.phone' => (string) ($institute['phone'] ?? ''),
            'caller.name' => (string) ($sender?->name ?? ''),
            'caller.mobile' => (string) ($sender?->mobile ?? ''),
            'campaign.topic' => (string) ($campaign?->campaignVariable('topic') ?? ''),
            'campaign.subject' => (string) ($campaign?->campaignVariable('subject') ?? ''),
            'campaign.date' => $this->resolveCampaignDate($campaign),
            'campaign.time' => $this->resolveCampaignTime($campaign),
            'activity.test_name' => (string) ($campaign?->campaignVariable('test_name') ?? ''),
            'activity.test_date' => (string) ($campaign?->campaignVariable('test_date') ?? ''),
            'activity.marks_summary' => $this->resolveStudentMarksSummary($campaign, $student),
            'attendance.date' => $this->resolveAttendanceDate($campaign),
            'attendance.time' => $this->resolveCampaignTime($campaign),
            'attendance.status' => (string) data_get(
                $campaign?->campaign_variables,
                '_student_attendance_status.'.$student->id,
                '',
            ),
            default => '',
        };
    }

    /**
     * @param  list<string|null>  $sources
     * @return list<string>
     */
    public function resolveAll(
        array $sources,
        Student $student,
        ?User $sender = null,
        ?Enquiry $enquiry = null,
        ?WhatsAppCampaign $campaign = null,
    ): array {
        $params = array_map(
            fn (?string $source): string => filled($source)
                ? $this->resolve($source, $student, $sender, $enquiry, $campaign)
                : '',
            $sources,
        );

        $manual = $campaign?->campaignVariable('_manual', []);

        if (! is_array($manual)) {
            return $params;
        }

        foreach ($manual as $index => $value) {
            if (filled($value) && array_key_exists((int) $index, $params)) {
                $params[(int) $index] = (string) $value;
            }
        }

        return $params;
    }

    public function buildPreview(?string $body, array $templateParams): ?string
    {
        if (blank($body)) {
            return null;
        }

        $message = $body;

        foreach ($templateParams as $index => $value) {
            $message = str_replace('{{'.($index + 1).'}}', (string) $value, $message);
        }

        return $message;
    }

    protected function resolveCampaignDate(?WhatsAppCampaign $campaign): string
    {
        $explicit = trim((string) ($campaign?->campaignVariable('date') ?? ''));

        if ($explicit !== '') {
            return $explicit;
        }

        $attendanceDate = $campaign?->campaignVariable('attendance_date');

        if (filled($attendanceDate)) {
            return Carbon::parse((string) $attendanceDate)->format('Y-m-d');
        }

        return '';
    }

    protected function resolveCampaignTime(?WhatsAppCampaign $campaign): string
    {
        return trim((string) ($campaign?->campaignVariable('time') ?? ''));
    }

    protected function resolveAttendanceDate(?WhatsAppCampaign $campaign): string
    {
        $attendanceDate = $campaign?->campaignVariable('attendance_date');

        if (! filled($attendanceDate)) {
            return '';
        }

        return Carbon::parse((string) $attendanceDate)->format('d M Y');
    }

    protected function resolveEnrollmentNumber(Student $student, ?WhatsAppCampaign $campaign): string
    {
        $rollsByStudent = $campaign?->campaignVariable('_student_rolls', []);

        if (is_array($rollsByStudent)) {
            $fromCampaign = $rollsByStudent[$student->id]
                ?? $rollsByStudent[(string) $student->id]
                ?? null;

            if (filled($fromCampaign)) {
                return (string) $fromCampaign;
            }
        }

        $fromActive = $student->activeEnrollment?->enrollment_number;

        if (filled($fromActive)) {
            return (string) $fromActive;
        }

        $fromLatest = $student->enrollments()
            ->whereNotNull('enrollment_number')
            ->where('enrollment_number', '!=', '')
            ->latest('id')
            ->value('enrollment_number');

        return filled($fromLatest) ? (string) $fromLatest : '';
    }

    protected function resolveStudentMarksSummary(?WhatsAppCampaign $campaign, Student $student): string
    {
        $marksByStudent = $campaign?->campaignVariable('_student_marks', []);

        if (! is_array($marksByStudent) || $marksByStudent === []) {
            return '';
        }

        $summary = $marksByStudent[$student->id]
            ?? $marksByStudent[(string) $student->id]
            ?? null;

        return filled($summary) ? (string) $summary : '';
    }
}
