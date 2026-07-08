<?php

namespace App\Models;

use App\Enums\CourseStatus;
use App\Enums\ProgrammeCategory;
use App\Enums\DurationType;
use App\Support\DefaultCourse;
use App\Services\CourseFeeSyncService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $fillable = [
        'name',
        'code',
        'programme_category',
        'duration',
        'duration_type',
        'fee',
        'description',
        'status',
        'show_on_website',
    ];

    protected function casts(): array
    {
        return [
            'programme_category' => ProgrammeCategory::class,
            'duration_type' => DurationType::class,
            'status' => CourseStatus::class,
            'fee' => 'decimal:2',
            'duration' => 'integer',
            'show_on_website' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Course $course): void {
            if ($course->programme_category === null) {
                $course->programme_category = ProgrammeCategory::Custom;
            }
        });

        static::updated(function (Course $course): void {
            if (! $course->wasChanged('fee')) {
                return;
            }

            app(CourseFeeSyncService::class)->syncCourseToActiveEnrollments(
                $course,
                auth()->user(),
            );
        });
    }

    protected function code(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value ? strtoupper(trim($value)) : null,
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CourseStatus::Active);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function getDurationLabelAttribute(): string
    {
        $unit = $this->duration_type === DurationType::Years ? 'Year' : 'Month';

        if ($this->duration === 1) {
            return "{$this->duration} {$unit}";
        }

        return "{$this->duration} {$unit}s";
    }

    public function getFormattedFeeAttribute(): string
    {
        return '₹'.number_format((float) $this->fee, 2);
    }

    public function admissionSelectLabel(): string
    {
        return "{$this->name} · {$this->duration_label} · {$this->formatted_fee}";
    }

    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function installmentTemplates(): HasMany
    {
        return $this->hasMany(CourseInstallmentTemplate::class)->orderBy('sort_order');
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(CourseSubject::class)->orderBy('sort_order')->orderBy('name');
    }

    public function activeSubjects(): HasMany
    {
        return $this->hasMany(CourseSubject::class)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * @return array{can_delete: bool, reason: ?string}
     */
    public function deletionBlockReason(): array
    {
        if ($this->code === DefaultCourse::UNDECIDED_CODE) {
            return [
                'can_delete' => false,
                'reason' => 'This is a system course used for walk-in enquiries and cannot be deleted.',
            ];
        }

        $enquiryCount = $this->enquiries()->count();
        $enrollmentCount = $this->enrollments()->count();

        if ($enquiryCount > 0 || $enrollmentCount > 0) {
            $parts = [];

            if ($enquiryCount > 0) {
                $parts[] = $enquiryCount.' '.str('enquiry')->plural($enquiryCount);
            }

            if ($enrollmentCount > 0) {
                $parts[] = $enrollmentCount.' '.str('enrollment')->plural($enrollmentCount);
            }

            return [
                'can_delete' => false,
                'reason' => 'This course is linked to '.implode(' and ', $parts)
                    .'. Set status to Inactive instead of deleting — history must stay intact.',
            ];
        }

        return ['can_delete' => true, 'reason' => null];
    }
}
