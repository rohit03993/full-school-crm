<?php

namespace App\Services;

use App\Enums\LicenseFeature;
use App\Enums\ParentNoticeStatus;
use App\Enums\WhatsAppRecipientStatus;
use App\Models\Setting;
use App\Models\StudentConductRecord;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Support\FeatureGate;
use Illuminate\Support\Collection;

class StudentConductParentNoticeService
{
    public function __construct(
        protected WhatsAppCampaignService $campaigns,
        protected WhatsAppSettingsService $settings,
        protected StudentConductService $conduct,
    ) {}

    /**
     * @param  list<int|string>  $recordIds
     * @return array{sent: int, waiting: int, note: string}
     */
    public function approve(array $recordIds, User $staff): array
    {
        if (! $this->conduct->canApproveParentNotice($staff)) {
            return [
                'sent' => 0,
                'waiting' => 0,
                'note' => 'Only Super Admin or Academic coordinator can send the parent message.',
            ];
        }

        $records = $this->waitingRecords($recordIds);

        if ($records->isEmpty()) {
            return [
                'sent' => 0,
                'waiting' => 0,
                'note' => 'Select a report that is waiting for approval.',
            ];
        }

        if (! FeatureGate::enabled(LicenseFeature::WhatsApp)) {
            return [
                'sent' => 0,
                'waiting' => $records->count(),
                'note' => 'Reports stay waiting. WhatsApp is off, so parents were not messaged.',
            ];
        }

        if (! Setting::getValue('whatsapp.student_report_autosend_enabled')) {
            return [
                'sent' => 0,
                'waiting' => $records->count(),
                'note' => 'Reports stay waiting. Turn on Student report WhatsApp under Automations.',
            ];
        }

        $template = $this->settings->resolveAutomationTemplate(
            Setting::getValue('whatsapp.student_report_live_campaign_id'),
        );

        if (! $template) {
            return [
                'sent' => 0,
                'waiting' => $records->count(),
                'note' => 'Reports stay waiting. Pick the student report template under Automations.',
            ];
        }

        $sent = 0;
        $firstError = null;

        foreach ($records as $record) {
            $result = $this->queueOne($record, $staff, $template);

            if ($result['queued']) {
                $record->update([
                    'parent_notice_status' => ParentNoticeStatus::Sent,
                    'parent_notice_sent_at' => now(),
                    'parent_notice_by_user_id' => $staff->id,
                ]);
                $sent++;

                continue;
            }

            $firstError ??= $result['message'];
        }

        $waiting = $records->count() - $sent;

        if ($sent === 0) {
            return [
                'sent' => 0,
                'waiting' => $waiting,
                'note' => $firstError ?: 'Reports stay waiting. The parent message was not sent.',
            ];
        }

        $note = 'Parent message sent for '.$sent.($sent === 1 ? ' student.' : ' students.');

        if ($waiting > 0) {
            $note .= ' '.$waiting.' still waiting.'.($firstError ? ' '.$firstError : '');
        }

        return ['sent' => $sent, 'waiting' => $waiting, 'note' => $note];
    }

    /**
     * @param  list<int|string>  $recordIds
     * @return array{declined: int, note: string}
     */
    public function decline(array $recordIds, User $staff): array
    {
        if (! $this->conduct->canApproveParentNotice($staff)) {
            return [
                'declined' => 0,
                'note' => 'Only Super Admin or Academic coordinator can decide the parent message.',
            ];
        }

        $records = $this->waitingRecords($recordIds);

        if ($records->isEmpty()) {
            return [
                'declined' => 0,
                'note' => 'Select a report that is waiting for approval.',
            ];
        }

        foreach ($records as $record) {
            $record->update([
                'parent_notice_status' => ParentNoticeStatus::Declined,
                'parent_notice_by_user_id' => $staff->id,
            ]);
        }

        $count = $records->count();

        return [
            'declined' => $count,
            'note' => 'Parent message will not be sent for '.$count.($count === 1 ? ' report.' : ' reports.'),
        ];
    }

    /**
     * @param  list<int|string>  $recordIds
     * @return Collection<int, StudentConductRecord>
     */
    protected function waitingRecords(array $recordIds): Collection
    {
        $ids = collect($recordIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return StudentConductRecord::query()
            ->with(['student.activeBatchStudent.batch', 'student.activeEnrollment'])
            ->whereIn('id', $ids)
            ->where('parent_notice_status', ParentNoticeStatus::Waiting->value)
            ->get();
    }

    /**
     * @return array{queued: bool, message: string}
     */
    protected function queueOne(StudentConductRecord $record, User $staff, WhatsAppTemplate $template): array
    {
        $student = $record->student;

        if (! $student || blank($student->mobile)) {
            return ['queued' => false, 'message' => 'Add a parent mobile number before sending.'];
        }

        try {
            $className = trim((string) ($student->activeBatchStudent?->batch?->displayLabel() ?? ''));
            $campaign = $this->campaigns->createCampaign([
                'name' => 'Student report · '.$student->name.' · '.now()->format('d M H:i'),
                'whatsapp_template_id' => $template->id,
                'student_ids' => [$student->id],
                'campaign_variables' => [
                    'audience_source' => 'student_report',
                    'report_class' => $className !== '' ? $className : 'Class',
                    'report_date' => $record->recorded_on?->timezone((string) config('app.timezone'))->format('d M Y')
                        ?? now()->timezone((string) config('app.timezone'))->format('d M Y'),
                    'report_type' => $record->typeLabelList(),
                    'report_fine' => $record->fine_charged
                        ? '₹'.number_format((int) $record->amount)
                        : 'No fine',
                    'report_remark' => trim((string) $record->note),
                    '_student_ids' => [$student->id],
                    '_student_conduct_record_id' => $record->id,
                ],
            ], $staff);

            $campaign = $this->campaigns->queueCampaign($campaign, $staff, true);
            $recipient = $campaign->recipients()->first();

            if ($recipient?->status === WhatsAppRecipientStatus::Failed) {
                return ['queued' => false, 'message' => 'WhatsApp send failed for '.$student->name.'.'];
            }

            return ['queued' => true, 'message' => 'WhatsApp queued to parent.'];
        } catch (\Throwable $exception) {
            return ['queued' => false, 'message' => $exception->getMessage()];
        }
    }
}
