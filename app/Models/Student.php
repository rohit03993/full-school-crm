<?php

namespace App\Models;

use App\Enums\AuthMethod;
use App\Enums\CallStatus;
use App\Enums\DocumentType;
use App\Enums\Gender;
use App\Enums\StudentCategory;
use App\Enums\StudentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class Student extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'father_name',
        'date_of_birth',
        'gender',
        'mobile',
        'mobile_import_note',
        'alternate_mobile',
        'email',
        'address',
        'city',
        'state',
        'pincode',
        'category',
        'status',
        'portal_password',
        'auth_method',
        'total_calls',
        'last_call_at',
        'last_call_status',
        'last_call_notes',
        'next_call_followup_at',
        'is_call_blocked',
        'call_blocked_reason',
        'call_blocked_at',
        'custom_data',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'gender' => Gender::class,
            'category' => StudentCategory::class,
            'status' => StudentStatus::class,
            'auth_method' => AuthMethod::class,
            'last_call_at' => 'datetime',
            'last_call_status' => CallStatus::class,
            'next_call_followup_at' => 'datetime',
            'is_call_blocked' => 'boolean',
            'call_blocked_at' => 'datetime',
            'total_calls' => 'integer',
            'custom_data' => 'array',
        ];
    }

    public function calls(): HasMany
    {
        return $this->hasMany(StudentCall::class);
    }

    public function whatsappMessages(): HasMany
    {
        return $this->hasMany(WhatsAppCampaignRecipient::class);
    }

    public function lastCall(): HasOne
    {
        return $this->hasOne(StudentCall::class)->latestOfMany('called_at');
    }

    public function isCallable(): bool
    {
        if ($this->is_call_blocked) {
            return false;
        }

        return filled($this->mobile);
    }

    public function telUrl(): ?string
    {
        $digits = $this->dialableMobile();

        if (strlen($digits) < 10) {
            return null;
        }

        return 'tel:+91'.substr($digits, -10);
    }

    public function dialableMobile(): string
    {
        $digits = preg_replace('/\D/', '', (string) $this->mobile);

        return $digits ?? '';
    }

    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function enquiry(): HasOne
    {
        return $this->hasOne(Enquiry::class);
    }

    public function latestEnquiry(): HasOne
    {
        return $this->enquiry();
    }

    public function admission(): HasOne
    {
        return $this->hasOne(Admission::class);
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function activeEnrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class)->where('is_active', true);
    }

    public function batchStudents(): HasMany
    {
        return $this->hasMany(BatchStudent::class);
    }

    public function activeBatchStudent(): HasOne
    {
        return $this->hasOne(BatchStudent::class)->where('is_active', true);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function activityAttendances(): HasMany
    {
        return $this->hasMany(ActivityAttendance::class);
    }

    public function hasActiveBatch(): bool
    {
        if (! Schema::hasTable('batch_students')) {
            return false;
        }

        return $this->activeBatchStudent()->exists();
    }

    public function profilePhoto(): ?Document
    {
        $admission = $this->activeEnrollment?->admission;

        if (! $admission && $this->relationLoaded('admissions')) {
            $admission = $this->admissions->first();
        }

        if (! $admission) {
            return null;
        }

        return $admission->documentForType(DocumentType::Photo);
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
