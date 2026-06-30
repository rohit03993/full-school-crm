<?php

namespace App\Models;

use App\Enums\ResultDeclarationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResultDeclaration extends Model
{
    protected $fillable = [
        'group_key',
        'test_name',
        'session_date',
        'batch_id',
        'activity_type_id',
        'status',
        'declaration_date',
        'declared_by_user_id',
        'declared_at',
        'marksheet_issue_date',
        'marksheet_issued_by_user_id',
        'marksheet_issued_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'declaration_date' => 'date',
            'marksheet_issue_date' => 'date',
            'declared_at' => 'datetime',
            'marksheet_issued_at' => 'datetime',
            'status' => ResultDeclarationStatus::class,
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class);
    }

    public function declaredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declared_by_user_id');
    }

    public function marksheetIssuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marksheet_issued_by_user_id');
    }

    public function studentMarksheets(): HasMany
    {
        return $this->hasMany(StudentMarksheet::class);
    }

    public function isPublished(): bool
    {
        return $this->status === ResultDeclarationStatus::Published
            && $this->declared_at !== null;
    }

    public function marksheetsIssued(): bool
    {
        return $this->marksheet_issued_at !== null;
    }
}
