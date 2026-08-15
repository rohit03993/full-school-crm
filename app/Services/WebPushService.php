<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
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

        if (! Setting::getValue('push.enabled', '1')) {
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
        if (! Setting::getValue('push.fee_reminders_enabled', '1')) {
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
        if ($dueCount < 1 || ! Setting::getValue('push.followup_digest_enabled', '1')) {
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
}
