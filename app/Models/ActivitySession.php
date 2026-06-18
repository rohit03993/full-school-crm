<?php

namespace App\Models;

use App\Models\Concerns\HasActivityAttendance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivitySession extends Model
{
    use HasActivityAttendance;

    protected $fillable = [
        'activity_type_id',
        'title',
        'session_date',
        'batch_id',
        'metadata',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'metadata' => 'array',
        ];
    }

    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function displayTitle(): string
    {
        $parts = array_filter([
            $this->title,
            $this->activityType?->name,
            $this->batch?->name,
            $this->session_date?->format('d M Y'),
        ]);

        return implode(' · ', $parts);
    }

    public function metadataValue(string $key): mixed
    {
        return $this->metadata[$key] ?? null;
    }
}
