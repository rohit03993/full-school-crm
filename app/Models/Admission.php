<?php

namespace App\Models;

use App\Enums\AdmissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Admission extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'student_id',
        'enquiry_id',
        'admission_number',
        'tenth_board',
        'tenth_percentage',
        'twelfth_board',
        'twelfth_percentage',
        'graduation',
        'graduation_percentage',
        'course_fee',
        'discount_amount',
        'net_fee',
        'status',
        'staff_remarks',
        'submitted_at',
        'approved_by_user_id',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AdmissionStatus::class,
            'tenth_percentage' => 'decimal:2',
            'twelfth_percentage' => 'decimal:2',
            'graduation_percentage' => 'decimal:2',
            'course_fee' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'net_fee' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
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

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function enrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [
            AdmissionStatus::Submitted,
            AdmissionStatus::Rejected,
        ], true);
    }

    public function canBeApproved(): bool
    {
        return $this->status === AdmissionStatus::VerificationPending;
    }

    public function canAdjustFees(): bool
    {
        return $this->enrollment === null
            && $this->course_fee !== null;
    }

    public function calculatedNetFee(float $discountAmount): float
    {
        return max(0, (float) $this->course_fee - max(0, $discountAmount));
    }

    public function hasReviewableSubmission(): bool
    {
        return $this->submitted_at !== null
            || filled($this->tenth_board)
            || filled($this->twelfth_board)
            || filled($this->graduation)
            || $this->relationLoaded('documents')
                ? $this->documents->isNotEmpty()
                : $this->documents()->exists();
    }

    public function documentForType(\App\Enums\DocumentType $type): ?Document
    {
        return $this->documents->first(fn (Document $document): bool => $document->type === $type);
    }
}
