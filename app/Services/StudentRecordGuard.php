<?php

namespace App\Services;

use App\Enums\StudentStatus;
use App\Models\ActivityAttendance;
use App\Models\HomeworkCheck;
use App\Models\ParentFeeNotice;
use App\Models\Student;
use App\Models\StudentDrop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class StudentRecordGuard
{
    protected static int $bypassDepth = 0;

    public static function bypass(callable $callback): mixed
    {
        self::$bypassDepth++;

        try {
            return $callback();
        } finally {
            self::$bypassDepth--;
        }
    }

    public function allows(Student $student, mixed $at = null, bool $dateOnly = false): bool
    {
        return $this->denialMessage($student, $at, $dateOnly) === null;
    }

    public function denialMessage(Student $student, mixed $at = null, bool $dateOnly = false): ?string
    {
        try {
            $this->assertWritable($student, $at, $dateOnly);
        } catch (ValidationException $exception) {
            return (string) (collect($exception->errors())->flatten()->first() ?? 'This student is inactive.');
        }

        return null;
    }

    public function assertWritable(Student $student, mixed $at = null, bool $dateOnly = false): void
    {
        if (self::$bypassDepth > 0) {
            return;
        }

        if ($student->status === StudentStatus::Dropped) {
            throw ValidationException::withMessages([
                'student' => 'This student is inactive. No fees, attendance, or other records can be added or deleted until they are made active.',
            ]);
        }

        if ($at === null || blank($at)) {
            return;
        }

        $moment = $at instanceof Carbon ? $at->copy() : Carbon::parse($at);

        if ($this->insideClosedPeriod($student, $moment, $dateOnly)) {
            throw ValidationException::withMessages([
                'student' => 'This date is inside an inactive period. No record can be added or deleted for that date.',
            ]);
        }
    }

    public function assertModelWritable(Model $model): void
    {
        if (self::$bypassDepth > 0 || $this->isSystemOnlyUpdate($model)) {
            return;
        }

        $studentId = $model->getAttribute('student_id');

        if (! $studentId) {
            return;
        }

        $student = $model->relationLoaded('student') && $model->student
            ? $model->student
            : Student::query()->find($studentId);

        if (! $student instanceof Student) {
            return;
        }

        $moment = $this->businessMoment($model);

        $this->assertWritable($student, $moment['at'], $moment['date_only']);
    }

    /**
     * @return array{at: mixed, date_only: bool}
     */
    protected function businessMoment(Model $model): array
    {
        if ($model instanceof ActivityAttendance) {
            $session = $model->relationLoaded('attendable')
                ? $model->attendable
                : $model->attendable()->first();
            $sessionDate = $session?->session_date ?? null;

            if ($sessionDate) {
                return ['at' => $sessionDate, 'date_only' => true];
            }
        }

        foreach (['attendance_date', 'checked_on', 'recorded_on', 'visit_date', 'payment_date', 'issued_on', 'penalty_date'] as $column) {
            $value = $model->getAttribute($column);

            if (filled($value)) {
                return ['at' => $value, 'date_only' => true];
            }
        }

        foreach (['called_at', 'opened_at', 'viewed_at'] as $column) {
            $value = $model->getAttribute($column);

            if (filled($value)) {
                return ['at' => $value, 'date_only' => false];
            }
        }

        return ['at' => null, 'date_only' => false];
    }

    protected function isSystemOnlyUpdate(Model $model): bool
    {
        if (! $model->exists) {
            return false;
        }

        $dirty = array_values(array_diff(array_keys($model->getDirty()), ['updated_at']));
        $allowed = match ($model::class) {
            HomeworkCheck::class => ['notify_status', 'notified_at'],
            ParentFeeNotice::class => ['status', 'sent_at', 'whatsapp_campaign_id', 'whatsapp_campaign_recipient_id'],
            default => [],
        };

        return $dirty !== [] && $allowed !== [] && array_diff($dirty, $allowed) === [];
    }

    protected function insideClosedPeriod(Student $student, Carbon $moment, bool $dateOnly): bool
    {
        $drops = StudentDrop::query()
            ->where('student_id', $student->id)
            ->whereNotNull('reactivated_at')
            ->get(['id', 'dropped_at', 'reactivated_at']);

        foreach ($drops as $drop) {
            if (! $drop->dropped_at || ! $drop->reactivated_at) {
                continue;
            }

            if ($dateOnly) {
                $day = $moment->copy()->timezone(config('app.timezone'))->toDateString();
                $start = $drop->dropped_at->copy()->timezone(config('app.timezone'))->toDateString();
                $end = $drop->reactivated_at->copy()->timezone(config('app.timezone'))->toDateString();

                if ($day >= $start && $day < $end) {
                    return true;
                }

                continue;
            }

            if ($moment->gte($drop->dropped_at) && $moment->lt($drop->reactivated_at)) {
                return true;
            }
        }

        return false;
    }
}
