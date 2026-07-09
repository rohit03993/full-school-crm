<?php

namespace App\Models;

use App\Enums\ExamWindowStatus;
use App\Support\ClassSectionLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamWindow extends Model
{
    protected $fillable = [
        'batch_id',
        'activity_type_id',
        'test_name',
        'session_date',
        'test_key',
        'status',
        'created_by_user_id',
        'submitted_by_user_id',
        'submitted_at',
        'approved_by_user_id',
        'approved_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'status' => ExamWindowStatus::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(ExamWindowSubject::class)->orderBy('id');
    }

    public function displayLabel(): string
    {
        $batch = $this->relationLoaded('batch') ? $this->batch : $this->batch()->with('course')->first();

        $classLabel = $batch ? ClassSectionLabel::forBatch($batch, includeSession: false, includeShift: false) : '—';

        return "{$this->test_name} · {$classLabel}";
    }

    public function groupKey(): string
    {
        return $this->test_key;
    }
}
