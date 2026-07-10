<?php

namespace App\Models;

use App\Enums\CampusVisitOutcome;
use App\Enums\CampusVisitPurpose;
use App\Enums\VisitStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Visit extends Model
{
    /**
     * @var list<string>
     */
    public const PHONE_CALL_REMARKS = [
        'Outgoing call',
        'Incoming call',
    ];

    protected $fillable = [
        'student_id',
        'enquiry_id',
        'student_case_id',
        'visit_date',
        'staff_user_id',
        'discussion_summary',
        'remarks',
        'next_follow_up_date',
        'status',
        'campus_purpose',
        'campus_outcome',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'next_follow_up_date' => 'date',
            'status' => VisitStatus::class,
            'campus_purpose' => CampusVisitPurpose::class,
            'campus_outcome' => CampusVisitOutcome::class,
        ];
    }

    public function isCampusVisit(): bool
    {
        return $this->campus_purpose !== null || $this->campus_outcome !== null;
    }

    public function isPhoneCallLog(): bool
    {
        return $this->remarks !== null
            && in_array($this->remarks, self::PHONE_CALL_REMARKS, true);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeInPerson($query)
    {
        return $query->where(function ($query): void {
            $query->whereNull('remarks')
                ->orWhereNotIn('remarks', self::PHONE_CALL_REMARKS);
        });
    }

    public function displayStatusLabel(): string
    {
        if ($this->campus_outcome) {
            return $this->campus_outcome->label();
        }

        if ($this->campus_purpose) {
            return $this->campus_purpose->label();
        }

        return $this->status?->label() ?? '—';
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function studentCase(): BelongsTo
    {
        return $this->belongsTo(StudentCase::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }
}
