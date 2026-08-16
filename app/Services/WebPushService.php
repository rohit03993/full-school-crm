<?php

namespace App\Services;

use App\Models\HomeworkAssignment;
use App\Models\PushSubscription;
use App\Models\Student;
use App\Models\StudentCase;
use App\Models\User;
use App\Support\PushSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    public function isConfigured(): bool
    {
        if (! config('webpush.enabled')) {
            return false;
        }

        return filled(config('webpush.vapid.public_key'))
            && filled(config('webpush.vapid.private_key'));
    }

    public function canSend(): bool
    {
        return $this->isConfigured() && class_exists(WebPush::class);
    }

    public function publicKey(): ?string
    {
        $key = config('webpush.vapid.public_key');

        return filled($key) ? (string) $key : null;
    }

    /**
     * @param  array{endpoint: string, keys?: array{p256dh?: string, auth?: string}, contentEncoding?: string}  $payload
     */
    public function saveSubscription(array $payload, ?User $user = null, ?Student $student = null, string $audience = 'staff'): PushSubscription
    {
        $endpoint = (string) ($payload['endpoint'] ?? '');

        abort_if($endpoint === '', 422, 'Missing push endpoint.');

        return PushSubscription::query()->updateOrCreate(
            ['endpoint' => $endpoint],
            [
                'user_id' => $user?->id,
                'student_id' => $student?->id,
                'public_key' => $payload['keys']['p256dh'] ?? null,
                'auth_token' => $payload['keys']['auth'] ?? null,
                'content_encoding' => $payload['contentEncoding'] ?? 'aesgcm',
                'audience' => $audience,
                'last_used_at' => now(),
            ],
        );
    }

    public function forgetEndpoint(string $endpoint): void
    {
        PushSubscription::query()->where('endpoint', $endpoint)->delete();
    }

    /**
     * @param  array{title: string, body: string, url?: string, tag?: string}  $notification
     * @return array{sent: int, failed: int}
     */
    public function sendToUser(User $user, array $notification): array
    {
        return $this->sendToSubscriptions(
            PushSubscription::query()->where('user_id', $user->id)->where('audience', 'staff')->get(),
            $notification,
        );
    }

    /**
     * @param  array{title: string, body: string, url?: string, tag?: string}  $notification
     * @return array{sent: int, failed: int}
     */
    public function sendToStudent(Student $student, array $notification): array
    {
        return $this->sendToSubscriptions(
            PushSubscription::query()->where('student_id', $student->id)->where('audience', 'portal')->get(),
            $notification,
        );
    }

    /**
     * @param  Collection<int, PushSubscription>  $subscriptions
     * @param  array{title: string, body: string, url?: string, tag?: string}  $notification
     * @return array{sent: int, failed: int}
     */
    public function sendToSubscriptions(Collection $subscriptions, array $notification): array
    {
        if (! $this->canSend() || $subscriptions->isEmpty()) {
            return ['sent' => 0, 'failed' => 0];
        }

        if (! PushSettings::enabled()) {
            return ['sent' => 0, 'failed' => 0];
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => (string) config('webpush.vapid.subject'),
                'publicKey' => (string) config('webpush.vapid.public_key'),
                'privateKey' => (string) config('webpush.vapid.private_key'),
            ],
        ]);

        $payload = json_encode([
            'title' => $notification['title'],
            'body' => $notification['body'],
            'url' => $notification['url'] ?? url('/app'),
            'tag' => $notification['tag'] ?? 'crm',
        ], JSON_UNESCAPED_SLASHES);

        foreach ($subscriptions as $row) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $row->endpoint,
                    'publicKey' => $row->public_key,
                    'authToken' => $row->auth_token,
                    'contentEncoding' => $row->content_encoding ?: 'aesgcm',
                ]),
                (string) $payload,
            );
        }

        $sent = 0;
        $failed = 0;

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $sent++;
                PushSubscription::query()
                    ->where('endpoint', $report->getEndpoint())
                    ->update(['last_used_at' => now()]);

                continue;
            }

            $failed++;
            $code = $report->getResponse()?->getStatusCode();

            if (in_array($code, [404, 410], true)) {
                $this->forgetEndpoint($report->getEndpoint());
            } else {
                Log::warning('Web push failed', [
                    'endpoint' => $report->getEndpoint(),
                    'reason' => $report->getReason(),
                ]);
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Fee due alerts for portal parents/students.
     *
     * @param  Collection<int, array{student_id: int, pending?: float|int|string}>  $rows
     */
    public function notifyFeeReminders(Collection $rows): int
    {
        if (! PushSettings::feeRemindersEnabled()) {
            return 0;
        }

        $sent = 0;

        foreach ($rows as $row) {
            $student = Student::query()->find($row['student_id'] ?? null);

            if (! $student) {
                continue;
            }

            $amount = isset($row['pending']) ? (float) $row['pending'] : null;
            $body = $amount !== null && $amount > 0
                ? 'Fee pending: ₹'.number_format($amount, 0).'. Open the app to view details.'
                : 'You have a fee reminder from the institute. Open the app for details.';

            $result = $this->sendToStudent($student, [
                'title' => 'Fee reminder',
                'body' => $body,
                'url' => url('/portal').'#fees',
                'tag' => 'fee-reminder',
            ]);

            $sent += $result['sent'];
        }

        return $sent;
    }

    /**
     * Morning digest for staff with due follow-ups.
     */
    public function notifyStaffFollowUpDigest(User $user, int $dueCount): array
    {
        if ($dueCount < 1 || ! PushSettings::followUpDigestEnabled()) {
            return ['sent' => 0, 'failed' => 0];
        }

        return $this->sendToUser($user, [
            'title' => 'Follow-ups due today',
            'body' => $dueCount === 1
                ? '1 follow-up needs attention.'
                : "{$dueCount} follow-ups need attention.",
            'url' => url('/admin/follow-ups'),
            'tag' => 'followups-due',
        ]);
    }

    public function notifyLeadAssigned(User $staff, string $studentName, ?string $profileUrl = null): array
    {
        if (! PushSettings::leadAssignedEnabled()) {
            return ['sent' => 0, 'failed' => 0];
        }

        return $this->sendToUser($staff, [
            'title' => 'Lead assigned for calling',
            'body' => "{$studentName} was assigned to you for telecalling.",
            'url' => $profileUrl ?: url('/admin'),
            'tag' => 'lead-assigned',
        ]);
    }

    public function notifyVisitAssigned(User $staff, string $studentName, ?string $profileUrl = null): array
    {
        if (! PushSettings::visitAssignedEnabled()) {
            return ['sent' => 0, 'failed' => 0];
        }

        return $this->sendToUser($staff, [
            'title' => 'Meeting assigned to you',
            'body' => "{$studentName} was assigned for a campus meeting.",
            'url' => $profileUrl ?: url('/admin'),
            'tag' => 'visit-assigned',
        ]);
    }

    public function notifyCaseAssigned(User $staff, StudentCase $case, string $studentName, ?string $url = null): array
    {
        if (! PushSettings::caseAssignedEnabled()) {
            return ['sent' => 0, 'failed' => 0];
        }

        return $this->sendToUser($staff, [
            'title' => 'Case assigned to you',
            'body' => "{$case->case_number} ({$studentName}) was assigned to you.",
            'url' => $url ?: url('/admin'),
            'tag' => 'case-assigned',
        ]);
    }

    public function notifyAttendancePunch(Student $student, string $state, string $time): array
    {
        if (! PushSettings::attendanceEnabled()) {
            return ['sent' => 0, 'failed' => 0];
        }

        $label = strtoupper($state) === 'OUT' ? 'OUT' : 'IN';
        $timeLabel = strlen($time) >= 5 ? substr($time, 0, 5) : $time;

        return $this->sendToStudent($student, [
            'title' => "Attendance {$label}",
            'body' => "{$student->name}: checked {$label} at {$timeLabel}.",
            'url' => url('/portal').'#attendance',
            'tag' => 'attendance-'.$label,
        ]);
    }

    public function notifyHomeworkPublished(HomeworkAssignment $assignment): int
    {
        if (! PushSettings::homeworkEnabled()) {
            return 0;
        }

        $assignment->loadMissing('batch');
        $title = $assignment->title ?: 'New homework';
        $batchName = $assignment->batch?->name;

        $students = \App\Models\BatchStudent::query()
            ->where('batch_id', $assignment->batch_id)
            ->where('is_active', true)
            ->with('student')
            ->get()
            ->pluck('student')
            ->filter();

        $sent = 0;

        foreach ($students as $student) {
            if (! $student instanceof Student) {
                continue;
            }

            $result = $this->sendToStudent($student, [
                'title' => 'New homework',
                'body' => $batchName
                    ? "{$title} · {$batchName}"
                    : $title,
                'url' => url('/portal').'#homework',
                'tag' => 'homework-'.$assignment->id,
            ]);

            $sent += $result['sent'];
        }

        return $sent;
    }

    /**
     * @param  Collection<int, Student>|iterable<int, Student>  $students
     */
    public function notifyMarksPublished(iterable $students, string $examName): int
    {
        if (! PushSettings::marksPublishedEnabled()) {
            return 0;
        }

        $sent = 0;

        foreach ($students as $student) {
            if (! $student instanceof Student) {
                continue;
            }

            $result = $this->sendToStudent($student, [
                'title' => 'Results published',
                'body' => filled($examName)
                    ? "{$examName} marks are now available in the app."
                    : 'Exam results are now available in the app.',
                'url' => url('/portal').'#marks',
                'tag' => 'marks-published',
            ]);

            $sent += $result['sent'];
        }

        return $sent;
    }

    public function notifyCaseUpdate(Student $student, StudentCase $case): array
    {
        if (! PushSettings::caseUpdateEnabled()) {
            return ['sent' => 0, 'failed' => 0];
        }

        return $this->sendToStudent($student, [
            'title' => 'Case update',
            'body' => "There is a new update on case {$case->case_number}.",
            'url' => url('/portal'),
            'tag' => 'case-update-'.$case->id,
        ]);
    }
}
