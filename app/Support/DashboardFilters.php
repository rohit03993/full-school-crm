<?php

namespace App\Support;

use App\Models\AcademicSession;
use Illuminate\Support\Carbon;

/**
 * Shared filter state for the admin dashboard.
 *
 * Widgets read the page filters through Filament's InteractsWithPageFilters and
 * hand them here so every number on the dashboard answers the same question.
 */
final class DashboardFilters
{
    public const RANGE_TODAY = 'today';

    public const RANGE_WEEK = 'week';

    public const RANGE_MONTH = 'month';

    public const RANGE_QUARTER = 'quarter';

    public const RANGE_SESSION = 'session';

    public const RANGE_CUSTOM = 'custom';

    public function __construct(
        public readonly ?int $sessionId,
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly ?int $courseId = null,
        public readonly ?int $batchId = null,
        public readonly string $range = self::RANGE_MONTH,
    ) {}

    public static function default(): self
    {
        return self::fromArray([]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function fromArray(array $filters): self
    {
        $sessionId = self::intOrNull($filters['academic_session_id'] ?? null);

        if (! array_key_exists('academic_session_id', $filters)) {
            $sessionId = AcademicSession::current()?->id;
        }

        $range = is_string($filters['range'] ?? null) ? $filters['range'] : self::RANGE_MONTH;
        [$from, $to] = self::resolveRange($range, $filters, $sessionId);

        return new self(
            sessionId: $sessionId,
            from: $from,
            to: $to,
            courseId: self::intOrNull($filters['course_id'] ?? null),
            batchId: self::intOrNull($filters['batch_id'] ?? null),
            range: $range,
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    protected static function resolveRange(string $range, array $filters, ?int $sessionId): array
    {
        $today = today();

        return match ($range) {
            self::RANGE_TODAY => [$today->copy()->startOfDay(), $today->copy()],
            self::RANGE_WEEK => [$today->copy()->subDays(6), $today->copy()],
            self::RANGE_QUARTER => [$today->copy()->subMonths(2)->startOfMonth(), $today->copy()],
            self::RANGE_SESSION => self::sessionRange($sessionId, $today),
            self::RANGE_CUSTOM => self::customRange($filters, $today),
            default => [$today->copy()->startOfMonth(), $today->copy()],
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected static function sessionRange(?int $sessionId, Carbon $today): array
    {
        $session = $sessionId !== null
            ? AcademicSession::query()->find($sessionId)
            : AcademicSession::current();

        $start = $session?->starts_on ? Carbon::parse($session->starts_on) : $today->copy()->startOfYear();
        $end = $session?->ends_on ? Carbon::parse($session->ends_on) : $today->copy();

        return [$start->startOfDay(), $end->min($today)->copy()];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    protected static function customRange(array $filters, Carbon $today): array
    {
        $from = filled($filters['from'] ?? null)
            ? Carbon::parse((string) $filters['from'])->startOfDay()
            : $today->copy()->startOfMonth();

        $to = filled($filters['to'] ?? null)
            ? Carbon::parse((string) $filters['to'])->startOfDay()
            : $today->copy();

        return $to->lt($from) ? [$to, $from] : [$from, $to];
    }

    protected static function intOrNull(mixed $value): ?int
    {
        return filled($value) ? (int) $value : null;
    }

    /**
     * Date the "as of" snapshots (attendance, pending fees) are calculated for.
     */
    public function asOf(): Carbon
    {
        return $this->to->copy();
    }

    public function isToday(): bool
    {
        return $this->asOf()->isSameDay(today());
    }

    public function rangeLabel(): string
    {
        if ($this->from->isSameDay($this->to)) {
            return $this->to->format('d M Y');
        }

        return $this->from->format('d M').' – '.$this->to->format('d M Y');
    }

    public function asOfLabel(): string
    {
        return $this->isToday() ? 'today' : $this->asOf()->format('d M Y');
    }

    /**
     * Whole months spanned by the range, clamped so charts stay readable.
     */
    public function monthsInRange(): int
    {
        $months = $this->from->copy()->startOfMonth()->diffInMonths($this->to->copy()->startOfMonth()) + 1;

        return max(1, min(24, (int) $months));
    }

    public function cacheKey(string $suffix): string
    {
        $fingerprint = implode('|', [
            $this->sessionId ?? 'all',
            $this->from->toDateString(),
            $this->to->toDateString(),
            $this->courseId ?? 'all',
            $this->batchId ?? 'all',
        ]);

        return 'crm.dashboard.'.$suffix.'.'.md5($fingerprint);
    }
}
