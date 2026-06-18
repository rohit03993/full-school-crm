<?php

namespace App\Support;

use App\Enums\InstituteType;
use App\Enums\MeetingFor;
use App\Enums\ProgrammeCategory;
use App\Models\Course;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;

class InstituteProfile
{
    public const SETTING_KEY = 'crm.institute_type';

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
        return collect(self::programmeCategories())
            ->mapWithKeys(fn (ProgrammeCategory $category): array => [$category->value => $category->label()])
            ->all();
    }

    public static function meetingFor(): MeetingFor
    {
        return self::type()->meetingFor();
    }

    /**
     * @return array<string, string>
     */
    public static function meetingForOptions(): array
    {
        $meetingFor = self::meetingFor();

        return [$meetingFor->value => $meetingFor->label()];
    }

  /**
     * @param  Builder<Course>  $query
     * @return Builder<Course>
     */
    public static function scopeCourses(Builder $query): Builder
    {
        return $query->whereIn(
            'programme_category',
            array_map(fn (ProgrammeCategory $category): string => $category->value, self::programmeCategories()),
        );
    }
}
