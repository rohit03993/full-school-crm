<?php

namespace App\Models;

use App\Enums\ParentNoticeStatus;
use App\Models\Concerns\BlocksInactiveStudentWrites;
use App\Support\ConductTypeOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentConductRecord extends Model
{
    use BlocksInactiveStudentWrites;

    protected $fillable = [
        'student_id',
        'recorded_on',
        'type_keys',
        'fine_charged',
        'amount',
        'note',
        'recorded_by_user_id',
        'parent_notice_status',
        'parent_notice_sent_at',
        'parent_notice_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'recorded_on' => 'date',
            'type_keys' => 'array',
            'fine_charged' => 'boolean',
            'amount' => 'integer',
            'parent_notice_status' => ParentNoticeStatus::class,
            'parent_notice_sent_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function parentNoticeBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_notice_by_user_id');
    }

    public function parentNoticeLabel(): ?string
    {
        return $this->parent_notice_status?->label();
    }

    public function typeLabelList(): string
    {
        $keys = is_array($this->type_keys) ? $this->type_keys : [];

        return ConductTypeOptions::labelsFor($keys);
    }

    public function fineSummary(): string
    {
        if (! $this->fine_charged) {
            return 'Without fine';
        }

        return 'Fine charged · ₹'.number_format((int) $this->amount);
    }
}
