<?php

namespace App\Models;

use App\Enums\LeadSource;
use App\Enums\VisitStatus;
use App\Enums\VisitType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Enquiry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'student_id',
        'enquiry_number',
        'course_id',
        'lead_source',
        'meeting_with_user_id',
        'calling_assigned_at',
        'calling_assigned_by_user_id',
        'meeting_for',
        'visit_type',
        'follow_up_reason',
        'latest_visit_status',
        'custom_data',
    ];

    protected function casts(): array
    {
        return [
            'lead_source' => LeadSource::class,
            'visit_type' => VisitType::class,
            'latest_visit_status' => VisitStatus::class,
            'calling_assigned_at' => 'datetime',
            'custom_data' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function meetingWith(): BelongsTo
    {
        return $this->belongsTo(User::class, 'meeting_with_user_id');
    }

    public function callingAssignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calling_assigned_by_user_id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function admission(): HasOne
    {
        return $this->hasOne(Admission::class);
    }
}
