<?php

namespace App\Filament\Forms;

use App\Models\Course;
use App\Models\Enquiry;
use App\Services\ConvertToAdmissionPresenter;
use App\Support\DefaultCourse;
use App\Support\InstituteProfile;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Collection;

class ConvertToAdmissionFormSchema
{
    /**
     * @param  Collection<int, Enquiry>  $convertible
     * @return array{
     *     enquiry_id?: int|null,
     *     course_id?: int|null,
     *     discount_amount: int|float,
     *     course_fee_display: string,
     *     net_fee_display: string
     * }
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
            'discount_amount' => 0,
            'use_installment_plan' => false,
            'misc_fees' => [],
            'installment_plan' => [],
            'course_fee_display' => self::formatCourseFee($courseId),
            'net_fee_display' => self::formatNetFee($courseId, 0, []),
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
                ->afterStateUpdated(function (mixed $state, callable $set, Get $get) use ($convertible, $presenter): void {
                    $enquiry = $convertible->firstWhere('id', (int) $state);

                    if ($enquiry && ! $presenter->enquiryNeedsCourseSelection($enquiry)) {
                        $set('course_id', $enquiry->course_id);
                    } else {
                        $set('course_id', null);
                    }

                    self::syncFeeDisplays($set, $get('course_id'), $get('discount_amount'), $get('misc_fees'));
                });
        } else {
            $fields[] = Hidden::make('enquiry_id')
                ->default($convertible->first()?->id);
        }

        $fields[] = Select::make('course_id')
            ->label('Course for admission')
            ->options(fn (): array => self::courseOptions())
            ->getOptionLabelUsing(fn ($value): ?string => Course::query()->find($value)?->admissionSelectLabel())
            ->required()
            ->searchable()
            ->live()
            ->native(false)
            ->helperText('Each option shows duration and fee from Courses master.')
            ->afterStateUpdated(function (mixed $state, callable $set, Get $get): void {
                self::syncFeeDisplays($set, $state, $get('discount_amount'), $get('misc_fees'));
            });

        $fields[] = TextInput::make('course_fee_display')
            ->label('Course fee')
            ->disabled()
            ->dehydrated(false)
            ->extraInputAttributes(['class' => 'font-semibold']);

        $fields[] = TextInput::make('discount_amount')
            ->label('Discount (₹)')
            ->numeric()
            ->default(0)
            ->minValue(0)
            ->step(0.01)
            ->live(debounce: 300)
            ->helperText('Staff can apply an initial discount at conversion. Admin can change later with reason.')
            ->afterStateUpdated(function (mixed $state, callable $set, Get $get): void {
                self::syncFeeDisplays($set, $get('course_id'), $state, $get('misc_fees'));
            });

        $fields[] = TextInput::make('net_fee_display')
            ->label('Net fee')
            ->disabled()
            ->dehydrated(false)
            ->extraInputAttributes(['class' => 'font-bold text-primary-600']);

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

        return [...$fields, ...AdmissionFeePlanFormSchema::fields()];
    }

    /**
     * @return array<int, string>
     */
    public static function courseOptions(): array
    {
        return InstituteProfile::adminCoursesQuery(Course::query())
            ->active()
            ->where('code', '!=', DefaultCourse::UNDECIDED_CODE)
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (Course $course): array => [
                $course->id => $course->admissionSelectLabel(),
            ])
            ->all();
    }

    public static function syncFeeDisplays(callable $set, mixed $courseId, mixed $discount, mixed $miscFees = null): void
    {
        $set('course_fee_display', self::formatCourseFee($courseId));
        $set('net_fee_display', self::formatNetFee($courseId, $discount, $miscFees));
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
            return null;
        }

        return $enquiry->course_id;
    }

    public static function formatCourseFee(mixed $courseId): string
    {
        if (! $courseId) {
            return 'Select a course to see the fee.';
        }

        $course = Course::query()->find($courseId);

        if (! $course) {
            return '—';
        }

        if ((float) $course->fee <= 0) {
            return '₹0.00 — update fee in Courses admin';
        }

        return $course->formatted_fee.' · '.$course->duration_label;
    }

    public static function formatNetFee(mixed $courseId, mixed $discount, mixed $miscFees = null): string
    {
        if (! $courseId) {
            return '—';
        }

        $course = Course::query()->find($courseId);

        if (! $course) {
            return '—';
        }

        $discountAmount = max(0, (float) ($discount ?? 0));
        $miscTotal = round(collect(is_array($miscFees) ? $miscFees : [])->sum(
            fn (array $row): float => (float) ($row['amount'] ?? 0),
        ), 2);
        $net = max(0, (float) $course->fee - $discountAmount + $miscTotal);

        return '₹'.number_format($net, 2);
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
