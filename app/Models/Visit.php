<?php

namespace App\Models;

use App\Enums\VisitStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Visit extends Model
{
    protected $fillable = [
        'student_id',
        'enquiry_id',
        'visit_date',
        'staff_user_id',
        'discussion_summary',
        'remarks',
        'next_follow_up_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'next_follow_up_date' => 'date',
            'status' => VisitStatus::class,
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }
}
