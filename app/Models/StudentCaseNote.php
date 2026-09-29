<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentCaseNote extends Model
{
    public const KIND_UPDATE = 'update';

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
