<?php

namespace App\Services;

use App\Enums\HomeworkAssignmentStatus;
use App\Enums\LicenseFeature;
use App\Enums\WhatsAppRecipientStatus;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkCheck;
use App\Models\Setting;
use App\Models\User;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use Illuminate\Support\Facades\Log;

class HomeworkCheckWhatsAppService
{
    public function __construct(
        protected WhatsAppCampaignService $campaigns,
        protected WhatsAppSettingsService $settings,
        protected HomeworkStudentLinkService $studentLinks,
    ) {}

    /**
     * @return array{queued: bool, message: string, campaign_id: int|null}
     */
    public function notifyNotDone(HomeworkCheck $check, User $teacher, bool $wait = true): array
    {
        try {
            if (! FeatureGate::enabled(LicenseFeature::WhatsApp)) {
                return ['queued' => false, 'message' => 'WhatsApp is not enabled on your licence.', 'campaign_id' => null];
            }

            if (! Setting::getValue('whatsapp.homework_not_done_autosend_enabled')) {
                return [
                    'queued' => false,
                    'message' => 'Turn on Homework not done WhatsApp in '.CrmNavigation::whatsAppMenu('Automations').'.',
                    'campaign_id' => null,
                ];
            }

            $template = $this->settings->resolveAutomationTemplate(
                Setting::getValue('whatsapp.homework_not_done_live_campaign_id'),
            );

            if (! $template) {
                return [
                    'queued' => false,
                    'message' => 'No Homework not done live campaign/template selected in Automations.',
                    'campaign_id' => null,
                ];
            }

            $check->loadMissing(['student', 'batch.course', 'courseSubject', 'homeworkAssignment']);
            $student = $check->student;
            $mobile = trim((string) ($check->parent_mobile ?: $student?->mobile));

            if ($mobile === '' || ! $student) {
                return ['queued' => false, 'message' => 'Student has no parent mobile number on file.', 'campaign_id' => null];
            }

            $batch = $check->batch;
            $assignment = $check->homeworkAssignment ?? $this->assignmentForCheck($check);
            $classSection = $this->classSectionWithoutExtraClassWord($batch?->displayLabel() ?? '');
            $subjectName = trim((string) ($check->courseSubject?->name ?: $check->subject_name));
            $link = $assignment ? $this->studentLinks->publicUrlFor($assignment, $student) : '';
            $dateLabel = $this->dateLabel($check, $assignment);
            $title = $this->titleForParent($check, $assignment);
            $separateLines = (int) $template->param_count >= 7;

            $campaign = $this->campaigns->createCampaign([
                'name' => 'Homework not done · '.$student->name.' · '.now()->format('d M H:i'),
                'whatsapp_template_id' => $template->id,
                'student_ids' => [$student->id],
                'campaign_variables' => [
                    'audience_source' => 'homework_check',
                    'topic' => $separateLines
                        ? $title
                        : $this->topicForParent($check, $assignment, $dateLabel, $link),
                    'subject' => $subjectName !== '' ? $subjectName : (string) $check->subject_name,
                    'class_section' => $classSection !== '' ? $classSection : 'Class',
                    'date' => $check->checked_on?->toDateString() ?? now()->toDateString(),
                    'date_label' => $dateLabel,
                    'homework_link' => $link,
                    '_student_ids' => [$student->id],
                    '_homework_check_id' => $check->id,
                ],
            ], $teacher);

            $campaign = $this->campaigns->queueCampaign($campaign, $teacher, $wait);
            $recipient = $wait ? $campaign->recipients()->first() : null;

            if ($recipient?->status === WhatsAppRecipientStatus::Failed) {
                return [
                    'queued' => false,
                    'message' => 'WhatsApp send failed for this student.',
                    'campaign_id' => $campaign->id,
                ];
            }

            return [
                'queued' => true,
                'message' => 'WhatsApp queued to parent.',
                'campaign_id' => $campaign->id,
            ];
        } catch (\Throwable $exception) {
            Log::warning('Homework not-done WhatsApp failed: '.$exception->getMessage());

            return ['queued' => false, 'message' => $exception->getMessage(), 'campaign_id' => null];
        }
    }

    protected function assignmentForCheck(HomeworkCheck $check): ?HomeworkAssignment
    {
        if (! $check->batch_id || ! $check->course_subject_id || ! $check->checked_on) {
            return null;
        }

        return HomeworkAssignment::query()
            ->where('batch_id', $check->batch_id)
            ->where('course_subject_id', $check->course_subject_id)
            ->whereDate('homework_date', $check->checked_on)
            ->whereIn('status', [
                HomeworkAssignmentStatus::Approved->value,
                HomeworkAssignmentStatus::Sent->value,
            ])
            ->orderByDesc('id')
            ->first();
    }

    protected function classSectionWithoutExtraClassWord(string $label): string
    {
        $label = trim($label);
        $stripped = preg_replace('/^class\s+/iu', '', $label);

        return trim(is_string($stripped) ? $stripped : $label);
    }

    protected function dateLabel(HomeworkCheck $check, ?HomeworkAssignment $assignment): string
    {
        $fromHomework = $assignment?->homeworkDateLabel();

        if (filled($fromHomework)) {
            return (string) $fromHomework;
        }

        return $check->checked_on
            ? $check->checked_on->timezone((string) config('app.timezone'))->format('d M Y')
            : now()->timezone((string) config('app.timezone'))->format('d M Y');
    }

    protected function titleForParent(HomeworkCheck $check, ?HomeworkAssignment $assignment): string
    {
        $title = trim((string) $check->topic);

        if ($assignment && ($title === '' || $title === "Today's homework") && filled($assignment->title)) {
            $title = trim((string) $assignment->title);
        }

        return $title !== '' ? $title : "Today's homework";
    }

    protected function topicForParent(
        HomeworkCheck $check,
        ?HomeworkAssignment $assignment,
        string $dateLabel,
        string $link,
    ): string {
        $line = $this->titleForParent($check, $assignment);

        if ($dateLabel !== '') {
            $line .= ' on '.$dateLabel;
        }

        if ($link !== '') {
            $line .= '. Open: '.$link;
        }

        return $line;
    }
}
