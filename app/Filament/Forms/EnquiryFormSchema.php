<?php

namespace App\Filament\Forms;

use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Support\MeetingForOptions;
use App\Enums\StudentCategory;
use App\Enums\VisitStatus;
use App\Enums\VisitType;
use App\Enums\RoleName;
use App\Models\Course;
use App\Models\User;
use App\Services\CustomFieldService;
use App\Support\CustomFieldFormBuilder;
use App\Support\DefaultCourse;
use App\Support\InstituteProfile;
use Filament\Support\Icons\Heroicon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Collection;

class EnquiryFormSchema
{
    /**
     * Minimal fields for Search Student → new number flow (no sections, no scroll box).
     *
     * @return array<int, TextInput|Select|Textarea>
     */
    public static function forSearchPageQuickEntry(?string $prefillMobile = null): array
    {
        return [
            TextInput::make('name')
                ->label('Student name')
                ->required()
                ->maxLength(255)
                ->autofocus()
                ->placeholder('Full name'),
            Select::make('meeting_for')
                ->label('Meeting for')
                ->options(self::meetingForOptions())
                ->default(MeetingForOptions::defaultValue())
                ->required()
                ->native(false),
            Select::make('course_id')
                ->label('Course interest')
                ->placeholder('Not decided yet')
                ->options(fn () => InstituteProfile::adminCoursesQuery(Course::query())
                    ->active()
                    ->where('code', '!=', DefaultCourse::UNDECIDED_CODE)
                    ->orderBy('name')
                    ->pluck('name', 'id'))
                ->searchable()
                ->native(false),
            Textarea::make('discussion_summary')
                ->label('Note')
                ->placeholder('Walk-in, fees question, batch timing…')
                ->rows(2),
        ];
    }

    /**
     * Fast walk-in capture — only name, mobile & meeting for are required.
     *
     * @return array<int, Section>
     */
    public static function forQuickStaffEntry(?string $prefillMobile = null): array
    {
        return [
            Section::make('Quick Enquiry')
                ->description('30-second entry for walk-ins & calls. Complete full profile later on the student page.')
                ->icon(Heroicon::OutlinedBolt)
                ->columns([
                    'default' => 1,
                    'sm' => 2,
                ])
                ->schema([
                    TextInput::make('name')
                        ->label('Student Name')
                        ->required()
                        ->maxLength(255)
                        ->autofocus()
                        ->columnSpan(['default' => 1, 'sm' => 2]),
                    TextInput::make('mobile')
                        ->label('Mobile Number')
                        ->tel()
                        ->required()
                        ->default($prefillMobile)
                        ->disabled(filled($prefillMobile))
                        ->dehydrated()
                        ->maxLength(10)
                        ->inputMode('numeric')
                        ->rule('regex:/^[6-9]\d{9}$/'),
                    Select::make('meeting_for')
                        ->label('Meeting For')
                        ->options(self::meetingForOptions())
                        ->default(MeetingForOptions::defaultValue())
                        ->required()
                        ->native(false),
                    Select::make('course_id')
                        ->label('Course Interest')
                        ->placeholder('Not decided yet')
                        ->options(fn () => InstituteProfile::adminCoursesQuery(Course::query())
                            ->active()
                            ->where('code', '!=', DefaultCourse::UNDECIDED_CODE)
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->native(false)
                        ->columnSpan(['default' => 1, 'sm' => 2]),
                    Textarea::make('discussion_summary')
                        ->label('Quick Note')
                        ->placeholder('e.g. Walk-in, asked about fees & batch timing')
                        ->rows(2)
                        ->columnSpan(['default' => 1, 'sm' => 2]),
                ])
                ->compact(),
            Section::make('More details')
                ->description('Optional — add now or later from the student profile.')
                ->collapsed()
                ->columns(2)
                ->schema([
                    TextInput::make('father_name')
                        ->label("Father's Name")
                        ->maxLength(255),
                    TextInput::make('city')
                        ->maxLength(100),
                ]),
            ...CustomFieldFormBuilder::sections(CustomFieldService::ENTITY_ENQUIRY),
        ];
    }

    /**
     * @return array<int, Section>
     */
    public static function forNewStudent(?string $prefillMobile = null): array
    {
        return [
            Section::make('Personal Details')
                ->columns(2)
                ->schema(self::personalFields($prefillMobile)),
            Section::make('Address')
                ->columns(2)
                ->schema(self::addressFields()),
            Section::make('Category & Course')
                ->columns(2)
                ->schema([
                    Select::make('category')
                        ->options(self::categoryOptions())
                        ->default(StudentCategory::General->value)
                        ->required()
                        ->native(false),
                    ...self::courseFields(),
                ]),
            Section::make('Lead & Meeting')
                ->columns(2)
                ->schema(self::leadAndMeetingFields()),
            Section::make('Visit Details')
                ->columns(2)
                ->schema(self::visitFields()),
            ...CustomFieldFormBuilder::sections(CustomFieldService::ENTITY_ENQUIRY),
        ];
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function forExistingStudent(int $staffUserId): array
    {
        return [
            Select::make('meeting_for')
                ->label('Meeting For')
                ->options(self::meetingForOptions())
                ->default(MeetingForOptions::defaultValue())
                ->required()
                ->native(false),
            Select::make('course_id')
                ->label('Course Interest')
                ->placeholder('Not decided yet')
                ->options(fn () => InstituteProfile::adminCoursesQuery(Course::query())
                    ->active()
                    ->where('code', '!=', DefaultCourse::UNDECIDED_CODE)
                    ->orderBy('name')
                    ->pluck('name', 'id'))
                ->searchable()
                ->native(false),
            Textarea::make('discussion_summary')
                ->label('Quick Note')
                ->rows(2)
                ->placeholder('Reason for this enquiry'),
            ...CustomFieldFormBuilder::flatComponents(CustomFieldService::ENTITY_ENQUIRY),
        ];
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function visitActionFields(int $staffUserId, Collection $enquiries): array
    {
        return [
            Select::make('enquiry_id')
                ->label('Enquiry / Course')
                ->options(
                    $enquiries->mapWithKeys(fn ($enquiry) => [
                        $enquiry->id => $enquiry->enquiry_number.' — '.$enquiry->course?->name,
                    ]),
                )
                ->required()
                ->native(false)
                ->default($enquiries->first()?->id),
            DatePicker::make('visit_date')
                ->default(now())
                ->required()
                ->native(false),
            Select::make('staff_user_id')
                ->label('Staff')
                ->options(self::staffOptions())
                ->default($staffUserId)
                ->required()
                ->native(false),
            Textarea::make('discussion_summary')
                ->required()
                ->rows(3)
                ->columnSpanFull(),
            Textarea::make('remarks')
                ->rows(2)
                ->columnSpanFull(),
            DatePicker::make('next_follow_up_date')
                ->native(false),
            Select::make('status')
                ->options(self::visitStatusOptions())
                ->default(VisitStatus::Interested->value)
                ->required()
                ->native(false),
        ];
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected static function personalFields(?string $prefillMobile = null): array
    {
        return [
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('father_name')
                ->label("Father's Name")
                ->required()
                ->maxLength(255),
            DatePicker::make('date_of_birth')
                ->required()
                ->maxDate(now()->subDay())
                ->native(false),
            Select::make('gender')
                ->options(self::genderOptions())
                ->required()
                ->native(false),
            TextInput::make('mobile')
                ->tel()
                ->required()
                ->default($prefillMobile)
                ->maxLength(10)
                ->rule('regex:/^[6-9]\d{9}$/'),
            TextInput::make('alternate_mobile')
                ->tel()
                ->maxLength(10)
                ->rule('nullable|regex:/^[6-9]\d{9}$/'),
        ];
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected static function addressFields(): array
    {
        return [
            Textarea::make('address')
                ->rows(2)
                ->columnSpanFull(),
            TextInput::make('city')
                ->maxLength(100),
            TextInput::make('state')
                ->maxLength(100),
            TextInput::make('pincode')
                ->maxLength(6)
                ->rule('nullable|digits:6'),
        ];
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected static function courseFields(): array
    {
        return [
            Select::make('course_id')
                ->label('Course Interest')
                ->options(fn () => InstituteProfile::adminCoursesQuery(Course::query()->active()->orderBy('name'))->pluck('name', 'id'))
                ->required()
                ->searchable()
                ->live()
                ->native(false),
            Placeholder::make('course_info')
                ->label('Course Details')
                ->content(function (Get $get): string {
                    $courseId = $get('course_id');

                    if (! $courseId) {
                        return 'Select a course to see duration and fee.';
                    }

                    $course = Course::query()->find($courseId);

                    if (! $course) {
                        return '—';
                    }

                    return $course->duration_label.' · '.$course->formatted_fee;
                }),
        ];
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected static function leadAndMeetingFields(?int $defaultStaffId = null): array
    {
        return [
            Select::make('lead_source')
                ->options(self::leadSourceOptions())
                ->default(LeadSource::WalkIn->value)
                ->required()
                ->native(false),
            Select::make('meeting_with_user_id')
                ->label('Meeting With')
                ->options(self::staffOptions())
                ->default($defaultStaffId ?? auth()->id())
                ->required()
                ->native(false),
            Select::make('meeting_for')
                ->options(self::meetingForOptions())
                ->default(MeetingForOptions::defaultValue())
                ->required()
                ->native(false),
        ];
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected static function visitFields(): array
    {
        return [
            Select::make('visit_type')
                ->options(self::visitTypeOptions())
                ->default(VisitType::FirstVisit->value)
                ->required()
                ->live()
                ->native(false),
            Textarea::make('follow_up_reason')
                ->label('Reason For Visit')
                ->rows(2)
                ->required(fn (Get $get): bool => $get('visit_type') === VisitType::FollowUp->value)
                ->visible(fn (Get $get): bool => $get('visit_type') === VisitType::FollowUp->value)
                ->columnSpanFull(),
            Textarea::make('discussion_summary')
                ->label('Discussion Summary')
                ->rows(3)
                ->columnSpanFull(),
            Select::make('visit_status')
                ->label('Visit Status')
                ->options(self::visitStatusOptions())
                ->default(VisitStatus::Interested->value)
                ->required()
                ->native(false),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function genderOptions(): array
    {
        return collect(Gender::cases())
            ->mapWithKeys(fn (Gender $gender) => [$gender->value => $gender->label()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected static function categoryOptions(): array
    {
        return collect(StudentCategory::cases())
            ->mapWithKeys(fn (StudentCategory $category) => [$category->value => $category->label()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected static function leadSourceOptions(): array
    {
        return collect(LeadSource::cases())
            ->mapWithKeys(fn (LeadSource $source) => [$source->value => $source->label()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected static function meetingForOptions(): array
    {
        return InstituteProfile::meetingForOptions();
    }

    /**
     * @return array<string, string>
     */
    protected static function visitTypeOptions(): array
    {
        return collect(VisitType::cases())
            ->mapWithKeys(fn (VisitType $type) => [$type->value => $type->label()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected static function visitStatusOptions(): array
    {
        return collect(VisitStatus::cases())
            ->mapWithKeys(fn (VisitStatus $status) => [$status->value => $status->label()])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected static function staffOptions(): array
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', [
                RoleName::Staff->value,
                RoleName::SuperAdmin->value,
            ]))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
