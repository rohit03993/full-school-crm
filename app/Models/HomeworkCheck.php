<?php

namespace App\Models;

use App\Enums\HomeworkCheckNotifyStatus;
use App\Enums\HomeworkCheckStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeworkCheck extends Model
{
    protected $fillable = [
        'student_id',
        'batch_id',
        'course_subject_id',
        'subject_name',
        'topic',
        'status',
        'parent_mobile',
        'notify_status',
        'notified_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => HomeworkCheckStatus::class,
            'notify_status' => HomeworkCheckNotifyStatus::class,
            'notified_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function courseSubject(): BelongsTo
    {
        return $this->belongsTo(CourseSubject::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
