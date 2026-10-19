<?php

namespace App\Models;

use App\Services\StudentRecordGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentCaseNote extends Model
{
    public const KIND_UPDATE = 'update';

    protected static function booted(): void
    {
        $guard = function (StudentCaseNote $note): void {
            $case = $note->relationLoaded('studentCase')
                ? $note->studentCase
                : StudentCase::query()->find($note->student_case_id);

            $studentId = $case?->student_id;

            if (! $studentId) {
                return;
            }

            $student = Student::query()->find($studentId);

            if ($student instanceof Student) {
                app(StudentRecordGuard::class)->assertWritable($student);
            }
        };

        static::creating($guard);
        static::updating($guard);
        static::deleting($guard);
    }

    public const KIND_REOPEN = 'reopen';

    protected $fillable = [
        'student_case_id',
        'user_id',
        'kind',
        'body',
    ];

    public function studentCase(): BelongsTo
    {
        return $this->belongsTo(StudentCase::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isMeetingUpdate(): bool
    {
        return $this->kind === self::KIND_UPDATE;
    }
}
