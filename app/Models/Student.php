<?php

namespace App\Models;

use App\Enums\AuthMethod;
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
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'gender' => Gender::class,
            'category' => StudentCategory::class,
            'status' => StudentStatus::class,
            'auth_method' => AuthMethod::class,
        ];
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
}
