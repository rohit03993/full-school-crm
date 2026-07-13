<?php

namespace App\Filament\Forms;

use App\Models\Course;
use App\Models\Enquiry;
use App\Services\ConvertToAdmissionPresenter;
use App\Support\InstituteProfile;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Collection;

class ConvertToAdmissionFormSchema
{
    /**
     * @param  Collection<int, Enquiry>  $convertible
     * @return array{enquiry_id?: int|null, course_id?: int|null}
     */
    public static function initialState(
        Collection $convertible,
        ConvertToAdmissionPresenter $presenter,
    ): array {
        $enquiryId = $presenter->defaultEnquiryId($convertible);
        $courseId = self::defaultCourseId($convertible, $presenter, $enquiryId);

        return [
            'enquiry_id' => $enquiryId,
            'course_id' => $courseId,
        ];
    }

    /**
     * @param  Collection<int, Enquiry>  $convertible
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function fields(Collection $convertible, ConvertToAdmissionPresenter $presenter): array
    {
        $fields = [];

        if ($convertible->count() > 1) {
            $fields[] = Select::make('enquiry_id')
                ->label('Enquiry')
                ->options($presenter->enquiryOptions($convertible))
                ->required()
                ->live()
                ->native(false)
                ->helperText($presenter->selectionWarning($convertible))
                ->afterStateUpdated(function (mixed $state, callable $set) use ($convertible, $presenter): void {
                    $enquiry = $convertible->firstWhere('id', (int) $state);

                    if (
                        $enquiry
                        && ! $presenter->enquiryNeedsCourseSelection($enquiry)
                        && InstituteProfile::isEnrollableCourseId((int) $enquiry->course_id)
                    ) {
                        $set('course_id', $enquiry->course_id);
                    } else {
                        $set('course_id', self::defaultEnrollableCourseId());
                    }
                });
        } else {
            $fields[] = Hidden::make('enquiry_id');
        }

        $fields[] = Select::make('course_id')
            ->label('Course for admission')
            ->options(fn (): array => self::courseOptions())
            ->getOptionLabelUsing(fn ($value): ?string => self::courseOptions()[(int) $value] ?? null)
            ->required()
            ->searchable()
            ->live()
            ->native(false)
            ->helperText('Only programmes with an active section in the current school year are listed. Fees are set after enrollment via Adjust Fees on the student profile.');

        $fields[] = Placeholder::make('course_fee_zero_warning')
            ->label('')
            ->content('This course has ₹0 fee. Set the fee in Courses admin before converting.')
            ->visible(function (Get $get): bool {
                $courseId = $get('course_id');

                if (! $courseId) {
                    return false;
                }

                $course = Course::query()->find($courseId);

                return $course && (float) $course->fee <= 0;
            })
            ->extraAttributes(['class' => 'text-sm font-medium text-danger-600 dark:text-danger-400'])
            ->columnSpanFull();

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function canSubmit(array $data): bool
    {
        $courseId = (int) ($data['course_id'] ?? 0);

        if ($courseId <= 0) {
            return false;
        }

        $course = Course::query()->find($courseId);

        if (! $course || (float) $course->fee <= 0) {
            return false;
        }

        return InstituteProfile::isEnrollableCourseId($courseId);
    }

    /**
     * @return array<int, string>
     */
    public static function courseOptions(): array
    {
        return InstituteProfile::enrollableCourseAdmissionOptions();
    }

    /**
     * @param  Collection<int, Enquiry>  $convertible
     */
    protected static function defaultCourseId(
        Collection $convertible,
        ConvertToAdmissionPresenter $presenter,
        mixed $enquiryId,
    ): ?int {
        $enquiry = self::resolveEnquiry($convertible, $presenter, $enquiryId);

        if (! $enquiry || $presenter->enquiryNeedsCourseSelection($enquiry)) {
            return self::defaultEnrollableCourseId();
        }

        if (InstituteProfile::isEnrollableCourseId((int) $enquiry->course_id)) {
            return $enquiry->course_id;
        }

        return self::defaultEnrollableCourseId();
    }

    protected static function defaultEnrollableCourseId(): ?int
    {
        $options = self::courseOptions();

        if (count($options) === 1) {
            return (int) array_key_first($options);
        }

        return null;
    }

    /**
     * @param  Collection<int, Enquiry>  $convertible
     */
    protected static function resolveEnquiry(
        Collection $convertible,
        ConvertToAdmissionPresenter $presenter,
        mixed $enquiryId,
    ): ?Enquiry {
        $id = $enquiryId ?? $presenter->defaultEnquiryId($convertible);

        return $convertible->firstWhere('id', (int) $id);
    }
}
