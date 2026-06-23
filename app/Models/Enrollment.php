<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Enrollment extends Model
{
    protected $fillable = [
        'student_id',
        'admission_id',
        'course_id',
        'academic_session_id',
        'enrollment_number',
        'enrolled_at',
        'status',
        'is_active',
        'id_card_path',
        'id_card_generated_at',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'status' => EnrollmentStatus::class,
            'is_active' => 'boolean',
            'id_card_generated_at' => 'datetime',
        ];
    }

    public function hasIdCard(): bool
    {
        return filled($this->id_card_path);
    }

    public function idCardPreviewUrl(): string
    {
        return route('admin.id-cards.preview', $this);
    }

    public function idCardDownloadUrl(): string
    {
        return route('admin.id-cards.download', $this);
    }

    public function portalIdCardDownloadUrl(): string
    {
        return route('portal.id-card.download');
    }

    public function canGenerateIdCard(): bool
    {
        return (float) ($this->feeStructure?->paid_amount ?? 0) > 0;
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function feeStructure(): HasOne
    {
        return $this->hasOne(FeeStructure::class);
    }
}
