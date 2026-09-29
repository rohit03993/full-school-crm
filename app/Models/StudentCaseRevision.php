<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentCaseRevision extends Model
{
    public const STATUS_APPLIED = 'applied';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'student_case_id',
        'user_id',
        'status',
        'title_changed',
        'summary_changed',
        'closing_note_changed',
        'note_changed',
        'old_title',
        'new_title',
        'old_summary',
        'new_summary',
        'old_closing_note',
        'new_closing_note',
        'note_id',
        'old_note',
        'new_note',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'title_changed' => 'boolean',
            'summary_changed' => 'boolean',
            'closing_note_changed' => 'boolean',
            'note_changed' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function studentCase(): BelongsTo
    {
        return $this->belongsTo(StudentCase::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
