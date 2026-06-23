<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentImportBatch extends Model
{
    protected $fillable = [
        'user_id',
        'academic_session_id',
        'course_id',
        'batch_id',
        'original_filename',
        'preview_rows',
        'duplicate_resolutions',
        'total_rows',
        'created_count',
        'updated_count',
        'skipped_count',
        'failed_count',
        'error_rows',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'created_count' => 'integer',
            'updated_count' => 'integer',
            'skipped_count' => 'integer',
            'failed_count' => 'integer',
            'error_rows' => 'array',
            'preview_rows' => 'array',
            'duplicate_resolutions' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class, 'import_batch_id');
    }
}
