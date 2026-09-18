<?php

namespace App\Services;

use App\Enums\CrmPermission;
use App\Enums\WhatsAppCampaignStatus;
use App\Models\ActivityAttendance;
use App\Models\ActivitySession;
use App\Models\ExamWindow;
use App\Models\ResultDeclaration;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Support\CrmAccess;
use App\Support\PublishedResultsGate;
use App\Support\StudentExamMarksMatrix;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ExamTestGroupService
{
    public function __construct(
        protected ActivityMarksWhatsAppService $marksWhatsApp,
        protected AuditService $audit,
    ) {}

    public function userCanManage(User $user): bool
    {
        return CrmAccess::can($user, CrmPermission::MarksImport);
    }

    /**
     * @param  list<string>  $groupKeys
     * @return array<string, array{allowed: bool, reason: ?string}>
     */
    public function deleteEligibility(array $groupKeys): array
    {
        $groupKeys = array_values(array_filter($groupKeys, fn (string $key): bool => filled($key)));
        $eligibility = [];

        foreach ($groupKeys as $groupKey) {
            $eligibility[$groupKey] = [
                'allowed' => true,
                'reason' => null,
            ];
        }

        if ($groupKeys === []) {
            return $eligibility;
        }

        $blockedByResults = [];

        if (Schema::hasTable('result_declarations')) {
            $blockedByResults = ResultDeclaration::query()
                ->whereIn('group_key', $groupKeys)
                ->get()
                ->filter(fn (ResultDeclaration $declaration): bool => $declaration->isPublished() || $declaration->marksheetsIssued())
                ->mapWithKeys(fn (ResultDeclaration $declaration): array => [$declaration->group_key => true])
                ->all();
        }

        $messagedKeys = $this->groupKeysWithParentMessages($groupKeys);

        foreach ($groupKeys as $groupKey) {
            if (isset($blockedByResults[$groupKey]) || PublishedResultsGate::isPublishedGroupKey($groupKey)) {
                $eligibility[$groupKey] = [
                    'allowed' => false,
                    'reason' => 'Results were published or marksheets issued.',
                ];

                continue;
            }

            if (isset($messagedKeys[$groupKey])) {
                $eligibility[$groupKey] = [
                    'allowed' => false,
                    'reason' => 'Marks were already sent to parents on WhatsApp.',
                ];
            }
        }

        return $eligibility;
    }

    public function deleteGroup(User $staff, string $groupKey): int
    {
        if (! $this->userCanManage($staff)) {
            throw ValidationException::withMessages([
                'exam' => 'You do not have permission to delete exams.',
            ]);
        }

        $eligibility = $this->deleteEligibility([$groupKey])[$groupKey] ?? [
            'allowed' => false,
            'reason' => 'This exam cannot be deleted.',
        ];

        if (! ($eligibility['allowed'] ?? false)) {
            throw ValidationException::withMessages([
                'exam' => $eligibility['reason'] ?? 'This exam cannot be deleted.',
            ]);
        }

        $sessions = $this->marksWhatsApp->sessionsForMarksKey($groupKey);

        if ($sessions->isEmpty()) {
            throw ValidationException::withMessages([
                'exam' => 'Exam not found.',
            ]);
        }

        $sessionIds = $sessions->pluck('id')->all();
        $deleted = 0;

        DB::transaction(function () use ($sessionIds, $groupKey, $staff, &$deleted): void {
            ActivityAttendance::query()
                ->where('attendable_type', (new ActivitySession)->getMorphClass())
                ->whereIn('attendable_id', $sessionIds)
                ->delete();

            $deleted = ActivitySession::query()->whereIn('id', $sessionIds)->delete();

            if (Schema::hasTable('result_declarations')) {
                ResultDeclaration::query()
                    ->where('group_key', $groupKey)
                    ->whereNull('declared_at')
                    ->delete();
            }

            $this->audit->log(
                'exam_test_deleted',
                null,
                [
                    'group_key' => $groupKey,
                    'session_ids' => $sessionIds,
                ],
                null,
                user: $staff,
            );
        });

        return $deleted;
    }

    public function renameGroup(User $staff, string $groupKey, string $newName): void
    {
        if (! $this->userCanManage($staff)) {
            throw ValidationException::withMessages([
                'exam' => 'You do not have permission to rename exams.',
            ]);
        }

        $groupKey = trim($groupKey);
        $newName = trim($newName);

        if ($groupKey === '') {
            throw ValidationException::withMessages([
                'exam' => 'Exam not found.',
            ]);
        }

        if ($newName === '') {
            throw ValidationException::withMessages([
                'name' => 'Enter an exam name.',
            ]);
        }

        $sessions = $this->marksWhatsApp->sessionsForMarksKey($groupKey);

        if ($sessions->isEmpty()) {
            throw ValidationException::withMessages([
                'exam' => 'Exam not found.',
            ]);
        }

        DB::transaction(function () use ($sessions, $groupKey, $newName, $staff): void {
            foreach ($sessions as $session) {
                $subject = StudentExamMarksMatrix::subjectForSession($session);
                $metadata = is_array($session->metadata) ? $session->metadata : [];
                $metadata['test_name'] = $newName;
                $session->metadata = $metadata;
                $session->title = "{$newName} — {$subject}";
                $session->save();
            }

            if (Schema::hasTable('exam_windows')) {
                ExamWindow::query()->where('test_key', $groupKey)->update(['test_name' => $newName]);
            }

            if (Schema::hasTable('result_declarations')) {
                ResultDeclaration::query()->where('group_key', $groupKey)->update(['test_name' => $newName]);
            }

            $this->audit->log(
                'exam_renamed',
                null,
                ['group_key' => $groupKey],
                ['group_key' => $groupKey, 'test_name' => $newName],
                user: $staff,
            );
        });
    }

    /**
     * @param  list<string>  $groupKeys
     * @return array<string, true>
     */
    protected function groupKeysWithParentMessages(array $groupKeys): array
    {
        if ($groupKeys === [] || ! Schema::hasTable('whatsapp_campaigns')) {
            return [];
        }

        $campaigns = WhatsAppCampaign::query()
            ->where('campaign_variables->audience_source', 'activity_marks')
            ->where(function ($query) use ($groupKeys): void {
                $query->whereIn('status', [
                    WhatsAppCampaignStatus::Queued,
                    WhatsAppCampaignStatus::Running,
                    WhatsAppCampaignStatus::Paused,
                    WhatsAppCampaignStatus::Completed,
                ])->orWhere('sent_count', '>', 0);
            })
            ->get(['id', 'campaign_variables', 'status', 'sent_count']);

        $blocked = [];

        foreach ($campaigns as $campaign) {
            $testKey = trim((string) $campaign->campaignVariable('test_key'));

            if ($testKey !== '' && in_array($testKey, $groupKeys, true)) {
                $blocked[$testKey] = true;
            }
        }

        return $blocked;
    }
}
