<?php

namespace App\Filament\Forms;

use App\Enums\BatchStatus;
use App\Enums\DocumentType;
use App\Enums\Gender;
use App\Enums\StudentCategory;
use App\Models\Admission;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Services\CustomFieldService;
use App\Support\CrmHint;
use App\Support\CustomFieldFormBuilder;
use App\Support\InstituteProfile;
use App\Support\StudentLabels;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;

class StudentProfileFormSchema
{
    /**
     * @return array<int, Section>
     */
    public static function forEdit(
        bool $hasActiveEnrollment = false,
        ?int $studentId = null,
        ?Admission $admission = null,
        bool $hasIdCard = false,
        ?Enrollment $enrollment = null,
    ): array {
        $sections = [];

        if ($admission !== null) {
            $photo = $admission->documentForType(DocumentType::Photo);
            $documentSchema = [];

            if ($photo !== null && $photo->isImage()) {
                $documentSchema[] = Placeholder::make('current_photo_preview')
                    ->label('Current photo')
                    ->content(fn (): HtmlString => new HtmlString(
                        '<img src="'.e($photo->previewUrl()).'" alt="Current student photo" class="h-24 w-20 rounded-lg object-cover ring-1 ring-gray-200 dark:ring-white/10" />'
                    ))
                    ->columnSpanFull();
            }

            foreach ([
                ['photo', 'Student photo', true],
                ['aadhaar', 'Aadhaar', false],
                ['marksheet', 'Marksheet', false],
                ['signature', 'Signature', false],
            ] as [$name, $label, $imageOnly]) {
                $documentSchema[] = self::documentUpload($name, $label, $imageOnly);
            }

            if ($hasIdCard) {
                $documentSchema[] = Toggle::make('regenerate_id_card')
                    ->label('Regenerate ID card with new photo')
                    ->helperText(CrmHint::field('regenerate_id_card'))
                    ->default(true)
                    ->columnSpanFull();
            }

            $sections[] = Section::make('Photo & documents')
                ->description('Optional — replace only the files you need.')
                ->compact()
                ->columns(2)
                ->schema($documentSchema);
        }

        if ($hasActiveEnrollment && $enrollment) {
            $sections[] = Section::make('Class & batch')
                ->description('Course fee updates follow the catalog when you change course.')
                ->compact()
                ->columns(2)
                ->schema([
                    Select::make('course_id')
                        ->label('Course')
                        ->options(InstituteProfile::activeCourseOptions())
                        ->default($enrollment->course_id)
                        ->required()
                        ->searchable()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(fn (Set $set): mixed => $set('batch_id', null)),
                    Select::make('batch_id')
                        ->label('Batch')
                        ->options(fn (Get $get): array => self::batchOptions(
                            (int) ($get('course_id') ?: $enrollment->course_id),
                            $enrollment->academic_session_id,
                        ))
                        ->searchable()
                        ->native(false)
                        ->placeholder('No batch assigned')
                        ->helperText('Only batches for this course and session are listed.'),
                    TextInput::make('enrollment_number')
                        ->label(StudentLabels::rollNumberLabel())
                        ->required()
                        ->maxLength(50)
                        ->helperText('Must be unique. ID card and receipts refresh if changed.')
                        ->extraInputAttributes(['class' => 'font-mono uppercase'])
                        ->columnSpanFull(),
                ]);
        }

        $sections = array_merge($sections, [
            Section::make('Personal details')
                ->compact()
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('father_name')
                        ->label("Father's name")
                        ->maxLength(255),
                    DatePicker::make('date_of_birth')
                        ->label('Date of birth')
                        ->maxDate(now()->subDay())
                        ->native(false),
                    Select::make('gender')
                        ->options(self::genderOptions())
                        ->native(false),
                    TextInput::make('mobile')
                        ->label('Mobile')
                        ->tel()
                        ->required()
                        ->maxLength(10)
                        ->rule('regex:/^[6-9]\d{9}$/')
                        ->rules([
                            Rule::unique('students', 'mobile')->ignore($studentId),
                        ])
                        ->helperText(CrmHint::field('mobile_unique')),
                    TextInput::make('alternate_mobile')
                        ->label('Alternate mobile')
                        ->tel()
                        ->maxLength(10)
                        ->rule('nullable|regex:/^[6-9]\d{9}$/'),
                    Select::make('category')
                        ->options(self::categoryOptions())
                        ->native(false),
                ]),
            Section::make('Address')
                ->compact()
                ->columns(2)
                ->schema([
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
                ]),
            ...CustomFieldFormBuilder::sections(CustomFieldService::ENTITY_STUDENT),
        ]);

        return $sections;
    }

    /**
     * @return array<int, string>
     */
    protected static function batchOptions(int $courseId, ?int $sessionId): array
    {
        if ($courseId <= 0) {
            return [];
        }

        $query = Batch::query()
            ->where('status', BatchStatus::Active)
            ->where('course_id', $courseId)
            ->orderBy('name');

        if ($sessionId) {
            $query->where('academic_session_id', $sessionId);
        }

        return $query
            ->pluck('name', 'id')
            ->all();
    }

    protected static function documentUpload(string $name, string $label, bool $imageOnly = false): FileUpload
    {
        $upload = FileUpload::make($name)
            ->label($label)
            ->helperText($imageOnly ? 'JPG or PNG · max 5 MB' : 'JPG, PNG or PDF · max 5 MB')
            ->maxSize(5120)
            ->disk('local')
            ->directory('temp-student-documents')
            ->visibility('private');

        if ($imageOnly) {
            return $upload
                ->image()
                ->imageEditor()
                ->imageEditorAspectRatioOptions([
                    '3:4' => 'Passport (3:4)',
                    '1:1' => 'Square',
                ])
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']);
        }

        return $upload->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf']);
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
}
