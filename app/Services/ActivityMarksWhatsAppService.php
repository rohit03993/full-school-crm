<?php

namespace App\Services;

use App\Models\ActivityAttendance;
use App\Models\ActivitySession;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Collection;

class ActivityMarksWhatsAppService
{
    public function __construct(
        protected WhatsAppCampaignService $campaigns,
    ) {}

    public function sessionsForMarksKey(string $marksKey): Collection
    {
        $query = ActivitySession::query();

        if (! str_contains($marksKey, '|')) {
            $query->where('metadata->test_key', $marksKey);
        } else {
            $parts = explode('|', $marksKey, 4);

            if (count($parts) !== 4) {
                return collect();
            }

            [$activityTypeId, $batchId, $date, $testLabel] = $parts;

            $query
                ->where('activity_type_id', (int) $activityTypeId)
                ->where('batch_id', (int) $batchId)
                ->whereDate('session_date', $date)
                ->where('metadata->test_name', $testLabel);
        }

        return $query->get(['id', 'metadata']);
    }

    /**
     * @return array<int, string> student_id => marks summary
     */
    public function buildStudentMarksSummaries(string $marksKey): array
    {
        $sessions = $this->sessionsForMarksKey($marksKey);

        if ($sessions->isEmpty()) {
            return [];
        }

        $sessionIds = $sessions->pluck('id')->all();
        $maxMarksBySession = $sessions->mapWithKeys(fn (ActivitySession $session): array => [
            $session->id => (float) ($session->metadataValue('max_marks') ?? 0),
        ]);
        $subjectBySession = $sessions->mapWithKeys(fn (ActivitySession $session): array => [
            $session->id => (string) ($session->metadataValue('subject') ?? 'Subject'),
        ]);

        $attendances = ActivityAttendance::query()
            ->where('attendable_type', ActivitySession::class)
            ->whereIn('attendable_id', $sessionIds)
            ->whereNotNull('marks_obtained')
            ->get(['student_id', 'attendable_id', 'marks_obtained']);

        /** @var array<int, list<string>> $parts */
        $parts = [];

        foreach ($attendances as $attendance) {
            $subject = $subjectBySession->get($attendance->attendable_id, 'Subject');
            $maxMarks = $maxMarksBySession->get($attendance->attendable_id);
            $mark = (float) $attendance->marks_obtained;
            $label = $maxMarks > 0
                ? "{$subject}: {$mark}/{$maxMarks}"
                : "{$subject}: {$mark}";

            $parts[(int) $attendance->student_id][] = $label;
        }

        $summaries = [];

        foreach ($parts as $studentId => $labels) {
            $summaries[$studentId] = implode(', ', $labels);
        }

        return $summaries;
    }

    /**
     * @return Collection<int, Student>
     */
    public function studentsWithMarks(string $marksKey): Collection
    {
        $summaries = $this->buildStudentMarksSummaries($marksKey);

        if ($summaries === []) {
            return collect();
        }

        return Student::query()
            ->whereIn('id', array_keys($summaries))
            ->whereNotNull('mobile')
            ->where('mobile', '!=', '')
            ->get();
    }

    public function createMarksCampaign(
        User $creator,
        WhatsAppTemplate $template,
        string $marksKey,
        string $testName,
        string $sessionDate,
    ): WhatsAppCampaign {
        $summaries = $this->buildStudentMarksSummaries($marksKey);
        $studentIds = array_keys($summaries);

        if ($studentIds === []) {
            throw new \InvalidArgumentException('No marks were found for this test.');
        }

        $rollNumbers = \App\Models\Enrollment::query()
            ->whereIn('student_id', $studentIds)
            ->where('is_active', true)
            ->whereNotNull('enrollment_number')
            ->where('enrollment_number', '!=', '')
            ->pluck('enrollment_number', 'student_id')
            ->all();

        return $this->campaigns->createCampaign([
            'name' => 'Marks · '.$testName,
            'whatsapp_template_id' => $template->id,
            'student_ids' => $studentIds,
            'campaign_variables' => [
                'audience_source' => 'activity_marks',
                'test_key' => $marksKey,
                'test_name' => $testName,
                'test_date' => $sessionDate,
                '_student_ids' => $studentIds,
                '_student_marks' => $summaries,
                '_student_rolls' => $rollNumbers,
            ],
        ], $creator);
    }

    public function queueMarksCampaign(
        User $creator,
        int $templateId,
        string $marksKey,
        string $testName,
        string $sessionDate,
    ): WhatsAppCampaign {
        if (! \App\Support\FeatureGate::enabled(\App\Enums\LicenseFeature::WhatsApp)) {
            throw new \RuntimeException('WhatsApp module is not enabled.');
        }

        $template = WhatsAppTemplate::query()
            ->whereKey($templateId)
            ->where('is_active', true)
            ->firstOrFail()
            ->ensureParamMappings();

        $campaign = $this->createMarksCampaign(
            $creator,
            $template,
            $marksKey,
            $testName,
            $sessionDate,
        );

        return $this->campaigns->queueCampaign($campaign, $creator);
    }
}
