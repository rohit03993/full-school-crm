<?php

namespace App\Services;

use App\Enums\LicenseFeature;
use App\Enums\MetaWhatsAppMessageStatus;
use App\Enums\OccasionWishStatus;
use App\Enums\OccasionWishType;
use App\Enums\StudentStatus;
use App\Enums\WhatsAppMessageSource;
use App\Enums\WhatsAppSendActor;
use App\Models\OccasionWish;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Support\FeatureGate;
use App\Support\InstituteSettings;
use App\Support\OccasionWishWhatsAppTemplate;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class OccasionWishService
{
    public const SETTING_ENABLED = 'whatsapp.occasion_wishes_enabled';

    public const SETTING_SEND_TIME = 'whatsapp.occasion_wishes_send_time';

    public const SETTING_REPORT_TIME = 'whatsapp.occasion_wishes_report_time';

    public const SETTING_ADMIN_MOBILE = 'whatsapp.occasion_wishes_admin_mobile';

    public const SETTING_MAX_RETRIES = 'whatsapp.occasion_wishes_max_retries';

    public const SETTING_STUDENT_BIRTHDAY_TEMPLATE = 'whatsapp.occasion_student_birthday_live_campaign_id';

    public const SETTING_STAFF_BIRTHDAY_TEMPLATE = 'whatsapp.occasion_staff_birthday_live_campaign_id';

    public const SETTING_STAFF_ANNIVERSARY_TEMPLATE = 'whatsapp.occasion_staff_anniversary_live_campaign_id';

    public const SETTING_REPORT_TEMPLATE = 'whatsapp.occasion_daily_report_live_campaign_id';

    public function __construct(
        protected WhatsAppDispatchService $dispatch,
        protected WhatsAppSettingsService $settings,
    ) {}

    /**
     * @return array{message: string, sent: int, failed: int, skipped: int, synced: int}
     */
    public function runScheduled(?Carbon $now = null): array
    {
        $now = ($now ?? now())->copy();

        if (! Schema::hasTable('occasion_wishes')) {
            return $this->result('Birthday wish list is not ready yet.');
        }

        if (! FeatureGate::enabled(LicenseFeature::WhatsApp)) {
            return $this->result('WhatsApp is off for this school.');
        }

        try {
            $synced = $this->syncForDate($now);
        } catch (\Throwable $exception) {
            Log::warning('Birthday wish list failed: '.$exception->getMessage());

            return $this->result($exception->getMessage());
        }

        if (! $this->enabled()) {
            return $this->result('Birthday wishes are switched off. Today\'s list was refreshed.', synced: $synced);
        }

        $sent = 0;
        $failed = 0;
        $skipped = 0;

        if ($this->isAfterClock($this->sendTime(), $now)) {
            [$sent, $failed, $skipped] = $this->sendOpenWishes($now);
        }

        if ($this->isAfterClock($this->reportTime(), $now)) {
            $this->sendDailyReport($now);
        }

        return $this->result(
            "Sent {$sent}, failed {$failed}, skipped {$skipped}.",
            $sent,
            $failed,
            $skipped,
            $synced,
        );
    }

    public function enabled(): bool
    {
        $value = Setting::getValue(self::SETTING_ENABLED, '0');

        return $value === '1' || $value === 1 || $value === true;
    }

    public function sendTime(): string
    {
        return $this->normalizeClock((string) Setting::getValue(self::SETTING_SEND_TIME, '08:00'), '08:00');
    }

    public function reportTime(): string
    {
        return $this->normalizeClock((string) Setting::getValue(self::SETTING_REPORT_TIME, '09:00'), '09:00');
    }

    public function maxRetries(): int
    {
        return max(1, min(5, (int) Setting::getValue(self::SETTING_MAX_RETRIES, 3)));
    }

    public function adminMobile(): string
    {
        return $this->normalizeMobile((string) Setting::getValue(self::SETTING_ADMIN_MOBILE, ''));
    }

    public function syncForDate(Carbon $date): int
    {
        if (! Schema::hasTable('occasion_wishes')) {
            return 0;
        }

        $saved = 0;

        foreach ($this->matchesForDate($date) as $match) {
            $this->upsertMatch($date, $match);
            $saved++;
        }

        return $saved;
    }

    /**
     * @return array{student_birthdays: int, staff_birthdays: int, anniversaries: int, sent: int, failed: int, skipped: int, pending: int}
     */
    public function summary(Carbon $date): array
    {
        $empty = [
            'student_birthdays' => 0,
            'staff_birthdays' => 0,
            'anniversaries' => 0,
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
            'pending' => 0,
        ];

        if (! Schema::hasTable('occasion_wishes')) {
            return $empty;
        }

        $rows = OccasionWish::query()
            ->whereDate('occasion_on', $date->toDateString())
            ->where('occasion', '!=', OccasionWishType::DailyReport->value)
            ->get();

        foreach ($rows as $row) {
            $occasionKey = match ($row->occasion) {
                OccasionWishType::StudentBirthday => 'student_birthdays',
                OccasionWishType::StaffBirthday => 'staff_birthdays',
                OccasionWishType::StaffAnniversary => 'anniversaries',
                default => null,
            };

            if ($occasionKey !== null) {
                $empty[$occasionKey]++;
            }

            $bucket = match ($row->status) {
                OccasionWishStatus::Sent, OccasionWishStatus::Delivered => 'sent',
                OccasionWishStatus::Failed => 'failed',
                OccasionWishStatus::Skipped => 'skipped',
                default => 'pending',
            };

            $empty[$bucket]++;
        }

        return $empty;
    }

    public function applyProviderStatus(string $wamid, MetaWhatsAppMessageStatus $status, ?string $detail = null): void
    {
        if ($wamid === '' || ! Schema::hasTable('occasion_wishes')) {
            return;
        }

        $wish = OccasionWish::query()->where('provider_message_id', $wamid)->first();

        if (! $wish || $wish->status === OccasionWishStatus::Delivered) {
            return;
        }

        if ($status === MetaWhatsAppMessageStatus::Delivered || $status === MetaWhatsAppMessageStatus::Read) {
            $wish->update([
                'status' => OccasionWishStatus::Delivered,
                'last_error' => null,
            ]);

            return;
        }

        if ($status === MetaWhatsAppMessageStatus::Failed) {
            $wish->update([
                'status' => OccasionWishStatus::Failed,
                'last_error' => filled($detail) ? mb_substr((string) $detail, 0, 500) : 'Delivery failed',
            ]);
        }
    }

    /**
     * @return list<array{occasion: string, recipient_key: string, student_id: ?int, user_id: ?int, name: string, mobile: string}>
     */
    public function matchesForDate(Carbon $date): array
    {
        $matches = [];

        Student::query()
            ->where('status', StudentStatus::Enrolled->value)
            ->whereNotNull('date_of_birth')
            ->where(fn ($query) => $this->whereMonthDay($query, 'date_of_birth', $date))
            ->orderBy('id')
            ->get(['id', 'name', 'mobile'])
            ->each(function (Student $student) use (&$matches): void {
                $matches[] = [
                    'occasion' => OccasionWishType::StudentBirthday->value,
                    'recipient_key' => 'student:'.$student->id,
                    'student_id' => $student->id,
                    'user_id' => null,
                    'name' => $this->displayName($student->name, 'Student'),
                    'mobile' => $this->normalizeMobile((string) $student->mobile),
                ];
            });

        if (! Schema::hasColumn('staff_profiles', 'date_of_birth')) {
            return $matches;
        }

        $this->staffWithDate($date, 'date_of_birth')
            ->each(function (User $staff) use (&$matches): void {
                $matches[] = $this->staffMatch($staff, OccasionWishType::StaffBirthday, 'birthday');
            });

        if (Schema::hasColumn('staff_profiles', 'marriage_anniversary')) {
            $this->staffWithDate($date, 'marriage_anniversary')
                ->each(function (User $staff) use (&$matches): void {
                    $matches[] = $this->staffMatch($staff, OccasionWishType::StaffAnniversary, 'anniversary');
                });
        }

        return $matches;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public function whereMonthDay($query, string $column, Carbon $date): void
    {
        $query->where(function ($inner) use ($column, $date): void {
            $inner->whereMonth($column, $date->month)->whereDay($column, $date->day);

            if ($date->month === 2 && $date->day === 28 && ! $date->isLeapYear()) {
                $inner->orWhere(function ($leap) use ($column): void {
                    $leap->whereMonth($column, 2)->whereDay($column, 29);
                });
            }
        });
    }

    /**
     * @param  array{occasion: string, recipient_key: string, student_id: ?int, user_id: ?int, name: string, mobile: string}  $match
     */
    private function upsertMatch(Carbon $date, array $match): OccasionWish
    {
        $existing = $this->findForDay($match['occasion'], $match['recipient_key'], $date);

        if ($existing) {
            $this->refreshOpenWish($existing, $match);

            return $existing;
        }

        try {
            $wish = OccasionWish::query()->create([
                'occasion' => $match['occasion'],
                'occasion_on' => $date->toDateString(),
                'recipient_key' => $match['recipient_key'],
                ...$this->freshAttributes($match),
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            $wish = $this->findForDay($match['occasion'], $match['recipient_key'], $date);

            if (! $wish) {
                throw $exception;
            }
        }

        if ($wish->wasRecentlyCreated) {
            return $wish;
        }

        $this->refreshOpenWish($wish, $match);

        return $wish;
    }

    private function findForDay(string $occasion, string $recipientKey, Carbon $date): ?OccasionWish
    {
        return OccasionWish::query()
            ->where('occasion', $occasion)
            ->where('recipient_key', $recipientKey)
            ->whereDate('occasion_on', $date->toDateString())
            ->first();
    }

    /**
     * @param  array{occasion: string, recipient_key: string, student_id: ?int, user_id: ?int, name: string, mobile: string}  $match
     * @return array<string, mixed>
     */
    private function freshAttributes(array $match): array
    {
        $mobile = $match['mobile'];

        return [
            'student_id' => $match['student_id'],
            'user_id' => $match['user_id'],
            'recipient_name' => $match['name'],
            'mobile' => $mobile !== '' ? $mobile : null,
            'status' => $mobile !== '' ? OccasionWishStatus::Pending : OccasionWishStatus::Skipped,
            'skip_reason' => $mobile !== '' ? null : 'No mobile number',
            'attempts' => 0,
        ];
    }

    /**
     * @param  array{name: string, mobile: string, student_id: ?int, user_id: ?int}  $match
     */
    private function refreshOpenWish(OccasionWish $wish, array $match): void
    {
        if (in_array($wish->status, [OccasionWishStatus::Sent, OccasionWishStatus::Delivered], true)) {
            return;
        }

        $mobile = $match['mobile'];
        $updates = [
            'recipient_name' => $match['name'],
            'student_id' => $match['student_id'],
            'user_id' => $match['user_id'],
        ];

        if ($mobile === '') {
            if ($wish->status !== OccasionWishStatus::Failed) {
                $updates['mobile'] = null;
                $updates['status'] = OccasionWishStatus::Skipped;
                $updates['skip_reason'] = 'No mobile number';
            }

            $wish->update($updates);

            return;
        }

        $updates['mobile'] = $mobile;

        if ($wish->status === OccasionWishStatus::Skipped || ($wish->status === OccasionWishStatus::Failed && $wish->mobile !== $mobile)) {
            $updates['status'] = OccasionWishStatus::Pending;
            $updates['skip_reason'] = null;
            $updates['last_error'] = null;
            $updates['attempts'] = 0;
            $updates['provider_message_id'] = null;
        }

        $wish->update($updates);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function sendOpenWishes(Carbon $date): array
    {
        $sent = 0;
        $failed = 0;
        $skipped = 0;

        $rows = OccasionWish::query()
            ->whereDate('occasion_on', $date->toDateString())
            ->where('occasion', '!=', OccasionWishType::DailyReport->value)
            ->whereIn('status', [OccasionWishStatus::Pending->value, OccasionWishStatus::Failed->value])
            ->orderBy('id')
            ->get();

        foreach ($rows as $wish) {
            $outcome = $this->sendWish($wish, $this->templateFor($wish->occasion));

            if ($outcome === 'sent') {
                $sent++;
            } elseif ($outcome === 'failed') {
                $failed++;
            } else {
                $skipped++;
            }
        }

        return [$sent, $failed, $skipped];
    }

    private function sendDailyReport(Carbon $date): void
    {
        $summary = $this->summary($date);
        $mobile = $this->adminMobile();
        $wish = $this->findForDay(OccasionWishType::DailyReport->value, 'admin', $date);

        if (! $wish) {
            try {
                $wish = OccasionWish::query()->create([
                    'occasion' => OccasionWishType::DailyReport->value,
                    'occasion_on' => $date->toDateString(),
                    'recipient_key' => 'admin',
                    'recipient_name' => 'Admin',
                    'mobile' => $mobile !== '' ? $mobile : null,
                    'status' => $mobile !== '' ? OccasionWishStatus::Pending : OccasionWishStatus::Skipped,
                    'skip_reason' => $mobile !== '' ? null : 'No admin WhatsApp number',
                    'attempts' => 0,
                ]);
            } catch (UniqueConstraintViolationException) {
                $wish = $this->findForDay(OccasionWishType::DailyReport->value, 'admin', $date);
            }
        }

        if (! $wish) {
            return;
        }

        if (in_array($wish->status, [OccasionWishStatus::Sent, OccasionWishStatus::Delivered], true)) {
            return;
        }

        if ($mobile === '') {
            $wish->update([
                'mobile' => null,
                'status' => OccasionWishStatus::Skipped,
                'skip_reason' => 'No admin WhatsApp number',
            ]);

            return;
        }

        if ($wish->status === OccasionWishStatus::Skipped || $wish->mobile !== $mobile) {
            $wish->update([
                'mobile' => $mobile,
                'status' => OccasionWishStatus::Pending,
                'skip_reason' => null,
                'attempts' => $wish->mobile !== $mobile ? 0 : $wish->attempts,
                'provider_message_id' => $wish->mobile !== $mobile ? null : $wish->provider_message_id,
            ]);
            $wish->refresh();
        }

        $institute = $this->instituteName();
        $params = [
            $date->format('d M Y'),
            $institute,
            (string) $summary['student_birthdays'],
            (string) $summary['staff_birthdays'],
            (string) $summary['anniversaries'],
            (string) $summary['sent'],
            (string) $summary['failed'],
            (string) $summary['skipped'],
        ];

        $this->sendWish($wish, $this->templateFor(OccasionWishType::DailyReport), $params);
    }

    /**
     * @param  list<string>|null  $params
     */
    private function sendWish(OccasionWish $wish, ?WhatsAppTemplate $template, ?array $params = null): string
    {
        if (in_array($wish->status, [OccasionWishStatus::Sent, OccasionWishStatus::Delivered, OccasionWishStatus::Skipped], true)) {
            return 'skipped';
        }

        if ($wish->attempts >= $this->maxRetries()) {
            return 'skipped';
        }

        if (blank($wish->mobile)) {
            $wish->update([
                'status' => OccasionWishStatus::Skipped,
                'skip_reason' => 'No mobile number',
            ]);

            return 'skipped';
        }

        if (! $template) {
            $this->rememberError($wish, 'No approved template selected');

            return 'skipped';
        }

        $params ??= [
            $this->displayName($wish->recipient_name, 'Friend'),
            $this->instituteName(),
        ];

        try {
            $result = $this->dispatch->send(
                phone: (string) $wish->mobile,
                templateParams: $params,
                templateName: $template->name,
                userName: $wish->recipient_name,
                expectedParamCount: (int) $template->param_count,
                languageCode: data_get($template->provider_meta, 'language_code', 'en'),
                logContext: [
                    'message_source' => WhatsAppMessageSource::Automation->value,
                    'send_actor' => WhatsAppSendActor::Automatic->value,
                    'source' => 'occasion_wish',
                    'student_id' => $wish->student_id,
                    'user_id' => $wish->user_id,
                    'occasion' => $wish->occasion instanceof OccasionWishType ? $wish->occasion->value : (string) $wish->occasion,
                ],
            );
        } catch (\Throwable $exception) {
            Log::warning('Birthday wish send failed: '.$exception->getMessage());
            $this->markFailed($wish, $exception->getMessage());

            return 'failed';
        }

        $status = (string) ($result['status'] ?? '');

        if (in_array($status, ['success', 'sent', 'queued'], true)) {
            $wish->update([
                'status' => OccasionWishStatus::Sent,
                'provider_message_id' => $result['message_id'] ?? null,
                'sent_at' => now(),
                'attempts' => $wish->attempts + 1,
                'last_error' => null,
                'skip_reason' => null,
            ]);

            return 'sent';
        }

        $this->markFailed($wish, (string) ($result['error'] ?? 'WhatsApp did not accept the message.'));

        return 'failed';
    }

    private function templateFor(OccasionWishType|string|null $occasion): ?WhatsAppTemplate
    {
        $type = $occasion instanceof OccasionWishType
            ? $occasion
            : OccasionWishType::tryFrom((string) $occasion);

        $key = match ($type) {
            OccasionWishType::StudentBirthday => self::SETTING_STUDENT_BIRTHDAY_TEMPLATE,
            OccasionWishType::StaffBirthday => self::SETTING_STAFF_BIRTHDAY_TEMPLATE,
            OccasionWishType::StaffAnniversary => self::SETTING_STAFF_ANNIVERSARY_TEMPLATE,
            OccasionWishType::DailyReport => self::SETTING_REPORT_TEMPLATE,
            default => null,
        };

        if ($key === null) {
            return null;
        }

        $stored = Setting::getValue($key);

        return $this->settings->resolveAutomationTemplate(filled($stored) ? (string) $stored : null);
    }

    /**
     * @return Collection<int, User>
     */
    private function staffWithDate(Carbon $date, string $column): Collection
    {
        return User::query()
            ->with('staffProfile')
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->where('is_platform_operator', false)->orWhereNull('is_platform_operator');
            })
            ->whereHas('staffProfile', function ($query) use ($date, $column): void {
                $query->whereNotNull($column)
                    ->where(fn ($inner) => $this->whereMonthDay($inner, $column, $date));
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{occasion: string, recipient_key: string, student_id: null, user_id: int, name: string, mobile: string}
     */
    private function staffMatch(User $staff, OccasionWishType $type, string $suffix): array
    {
        $staff->loadMissing('staffProfile');
        $mobile = $staff->staffProfile?->mobile ?: $staff->mobile;

        return [
            'occasion' => $type->value,
            'recipient_key' => 'staff:'.$staff->id.':'.$suffix,
            'student_id' => null,
            'user_id' => $staff->id,
            'name' => $this->displayName($staff->name, 'Staff member'),
            'mobile' => $this->normalizeMobile((string) $mobile),
        ];
    }

    private function markFailed(OccasionWish $wish, string $error): void
    {
        $wish->update([
            'status' => OccasionWishStatus::Failed,
            'attempts' => $wish->attempts + 1,
            'last_error' => mb_substr($error, 0, 500),
            'sent_at' => now(),
        ]);
    }

    private function rememberError(OccasionWish $wish, string $error): void
    {
        if ($wish->last_error === $error) {
            return;
        }

        $wish->update(['last_error' => $error]);
    }

    private function instituteName(): string
    {
        $name = trim(InstituteSettings::brandName());

        return $name !== '' ? $name : 'Our institute';
    }

    private function displayName(?string $name, string $fallback): string
    {
        $name = trim((string) $name);

        return $name !== '' ? $name : $fallback;
    }

    private function normalizeMobile(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        return preg_match('/^[6-9]\d{9}$/', $digits) === 1 ? $digits : '';
    }

    private function normalizeClock(string $raw, string $fallback): string
    {
        $raw = trim($raw);

        if (! preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $raw)) {
            return $fallback;
        }

        return strlen($raw) === 4 ? '0'.$raw : $raw;
    }

    private function isAfterClock(string $time, Carbon $now): bool
    {
        $start = Carbon::parse($now->toDateString().' '.$time, $now->timezone);

        return $now->greaterThanOrEqualTo($start);
    }

    /**
     * @return array{message: string, sent: int, failed: int, skipped: int, synced: int}
     */
    private function result(string $message, int $sent = 0, int $failed = 0, int $skipped = 0, int $synced = 0): array
    {
        return [
            'message' => $message,
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'synced' => $synced,
        ];
    }
}
