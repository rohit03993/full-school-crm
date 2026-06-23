<?php

namespace App\Support;

use App\Enums\CourseStatus;
use App\Enums\InstituteType;
use App\Enums\ProgrammeCategory;
use App\Models\Course;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;

class InstituteProfile
{
    public const SETTING_KEY = 'crm.institute_type';

    /**
     * Legacy setting — kept for existing installs; no longer shown in admin UI.
     */
    public static function type(): InstituteType
    {
        $value = Setting::getValue(self::SETTING_KEY, config('institute.type', InstituteType::School->value));

        return InstituteType::tryFrom((string) $value) ?? InstituteType::School;
    }

    public static function setType(InstituteType $type): void
    {
        Setting::setValue(self::SETTING_KEY, $type->value, 'crm');
    }

    /**
     * @return array<int, ProgrammeCategory>
     */
    public static function programmeCategories(): array
    {
        return self::type()->programmeCategories();
    }

    /**
     * @return array<string, string>
     */
    public static function programmeCategoryOptions(): array
    {
        return collect(ProgrammeCategory::cases())
            ->mapWithKeys(fn (ProgrammeCategory $category): array => [$category->value => $category->label()])
            ->all();
    }

    public static function meetingFor(): string
    {
        return MeetingForOptions::defaultValue();
    }

    /**
     * @return array<string, string>
     */
    public static function meetingForOptions(): array
    {
        return MeetingForOptions::formOptions();
    }

    /**
     * @deprecated Use adminCoursesQuery() — institute type no longer filters admin lists.
     *
     * @param  Builder<Course>  $query
     * @return Builder<Course>
     */
    public static function scopeCourses(Builder $query): Builder
    {
        return self::adminCoursesQuery($query);
    }

    /**
     * Programmes selectable in CRM forms (excludes system “Course Not Decided”).
     *
     * @param  Builder<Course>  $query
     * @return Builder<Course>
     */
    public static function adminCoursesQuery(Builder $query): Builder
    {
        return $query->where('code', '!=', DefaultCourse::UNDECIDED_CODE);
    }

    /**
     * Whether the course may be selected on the public website enquiry form.
     */
    public static function isPublicCourseId(int $courseId): bool
    {
        return self::publicCoursesQuery(Course::query())->whereKey($courseId)->exists();
    }

    /**
     * Programmes visible on the public website.
     *
     * @param  Builder<Course>  $query
     * @return Builder<Course>
     */
    public static function publicCoursesQuery(Builder $query): Builder
    {
        return self::adminCoursesQuery($query)
            ->where('status', CourseStatus::Active)
            ->where('show_on_website', true);
    }

    /**
     * Active programmes for CRM dropdowns.
     *
     * @return array<int, string>
     */
    public static function activeCourseOptions(bool $excludeUndecided = true): array
    {
        $query = Course::query()->active()->orderBy('name');

        if ($excludeUndecided) {
            $query = self::adminCoursesQuery($query);
        }

        return $query->pluck('name', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    public static function activeCourseAdmissionOptions(bool $excludeUndecided = true): array
    {
        $query = Course::query()->active()->orderBy('name');

        if ($excludeUndecided) {
            $query = self::adminCoursesQuery($query);
        }

        return $query
            ->get()
            ->mapWithKeys(fn (Course $course): array => [
                $course->id => $course->admissionSelectLabel(),
            ])
            ->all();
    }
}
