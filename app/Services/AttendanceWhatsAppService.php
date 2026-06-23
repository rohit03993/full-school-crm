<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\Batch;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class AttendanceWhatsAppService
{
    public function __construct(
        protected WhatsAppCampaignService $campaigns,
    ) {}

    /**
     * Queue WhatsApp to present students when attendance auto-send is enabled in settings.
     *
     * @param  array<int, string>  $marks  student_id => attendance status value
     * @return int|null Recipient count when queued, null when skipped
     */
    public function maybeQueueAfterBatchAttendance(Batch $batch, string $date, array $marks, User $staff): ?int
    {
        try {
            if (! Setting::getValue('whatsapp.attendance_autosend_enabled')) {
                return null;
            }

            $templateId = Setting::getValue('whatsapp.attendance_autosend_template_id');

            if (! $templateId) {
                return null;
            }

            $template = WhatsAppTemplate::query()
                ->whereKey($templateId)
                ->where('is_active', true)
                ->first();

            if (! $template) {
                return null;
            }

            $statusByStudent = $this->presentStatusLabels($marks);

            if ($statusByStudent === []) {
                return null;
            }

            $studentIds = array_keys($statusByStudent);

            $students = Student::query()
                ->whereIn('id', $studentIds)
                ->whereNotNull('mobile')
                ->where('mobile', '!=', '')
                ->get();

            if ($students->isEmpty()) {
                return null;
            }

            $eligibleStatuses = $students->mapWithKeys(
                fn (Student $student): array => [$student->id => $statusByStudent[$student->id] ?? '']
            )->all();

            $markedAt = now();
            $attendanceDate = Carbon::parse($date);

            $campaign = $this->campaigns->createCampaign([
                'name' => 'Attendance · '.$batch->name.' · '.$attendanceDate->format('d M Y'),
                'whatsapp_template_id' => $template->id,
                'batch_id' => $batch->id,
                'student_ids' => $students->pluck('id')->all(),
                'campaign_variables' => [
                    'audience_source' => 'attendance',
                    'attendance_date' => $date,
                    'date' => $attendanceDate->format('Y-m-d'),
                    'time' => $markedAt->format('H:i:s'),
                    'batch_name' => $batch->name,
                    '_student_ids' => $students->pluck('id')->all(),
                    '_student_attendance_status' => $eligibleStatuses,
                ],
            ], $staff);

            $this->campaigns->queueCampaign($campaign, $staff);

            return $students->count();
        } catch (\Throwable $exception) {
            Log::warning('Attendance WhatsApp failed: '.$exception->getMessage());

            return null;
        }
    }

    /**
     * @param  array<int, string>  $marks
     * @return array<int, string> student_id => status label
     */
    public function presentStatusLabels(array $marks): array
    {
        $labels = [];

        foreach ($marks as $studentId => $statusValue) {
            $status = AttendanceStatus::tryFrom((string) $statusValue);

            if ($status !== AttendanceStatus::Present) {
                continue;
            }

            $labels[(int) $studentId] = $status->label();
        }

        return $labels;
    }

    public function createAttendanceCampaign(
        User $creator,
        WhatsAppTemplate $template,
        Batch $batch,
        string $date,
        array $marks,
    ): WhatsAppCampaign {
        $statusByStudent = $this->presentStatusLabels($marks);

        if ($statusByStudent === []) {
            throw new \InvalidArgumentException('No present students to notify.');
        }

        $studentIds = array_keys($statusByStudent);

        $attendanceDate = Carbon::parse($date);
        $markedAt = now();

        return $this->campaigns->createCampaign([
            'name' => 'Attendance · '.$batch->name.' · '.$attendanceDate->format('d M Y'),
            'whatsapp_template_id' => $template->id,
            'batch_id' => $batch->id,
            'student_ids' => $studentIds,
            'campaign_variables' => [
                'audience_source' => 'attendance',
                'attendance_date' => $date,
                'date' => $attendanceDate->format('Y-m-d'),
                'time' => $markedAt->format('H:i:s'),
                'batch_name' => $batch->name,
                '_student_ids' => $studentIds,
                '_student_attendance_status' => $statusByStudent,
            ],
        ], $creator);
    }
}
