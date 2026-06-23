<?php

namespace App\Models;

use App\Enums\AdmissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Admission extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'student_id',
        'enquiry_id',
        'import_batch_id',
        'admission_number',
        'tenth_board',
        'tenth_percentage',
        'twelfth_board',
        'twelfth_percentage',
        'graduation',
        'graduation_percentage',
        'course_fee',
        'discount_amount',
        'discount_set_by_user_id',
        'net_fee',
        'use_installment_plan',
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
            'use_installment_plan' => 'boolean',
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

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(StudentImportBatch::class, 'import_batch_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function discountSetBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discount_set_by_user_id');
    }

    public function enrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class);
    }

    public function miscFees(): HasMany
    {
        return $this->hasMany(AdmissionMiscFee::class)->orderBy('sort_order');
    }

    public function installmentPlans(): HasMany
    {
        return $this->hasMany(AdmissionInstallmentPlan::class)
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function discountEntries(): HasMany
    {
        return $this->hasMany(FeeDiscountEntry::class)->orderBy('created_at');
    }

    public function miscFeesTotal(): float
    {
        if ($this->relationLoaded('miscFees')) {
            return round((float) $this->miscFees->sum('amount'), 2);
        }

        return round((float) $this->miscFees()->sum('amount'), 2);
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

    public function calculatedNetFee(float $discountAmount, float $miscFeesTotal = 0): float
    {
        return max(0, (float) $this->course_fee - max(0, $discountAmount) + max(0, $miscFeesTotal));
    }

    public function hasReviewableSubmission(): bool
    {
        if ($this->submitted_at !== null) {
            return true;
        }

        if (filled($this->tenth_board) || filled($this->twelfth_board) || filled($this->graduation)) {
            return true;
        }

        return $this->relationLoaded('documents')
            ? $this->documents->isNotEmpty()
            : $this->documents()->exists();
    }

    public function documentForType(\App\Enums\DocumentType $type): ?Document
    {
        return $this->documents->first(fn (Document $document): bool => $document->type === $type);
    }
}
