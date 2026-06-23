<?php

namespace App\Filament\Forms;

use App\Enums\DocumentType;
use App\Enums\Gender;
use App\Enums\StudentCategory;
use App\Models\Admission;
use App\Services\CustomFieldService;
use App\Models\Document;
use App\Support\CrmHint;
use App\Support\CustomFieldFormBuilder;
use App\Support\StudentLabels;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
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
    ): array {
        $sections = [];

        if ($admission !== null) {
            $photo = $admission->documentForType(DocumentType::Photo);
            $documentSchema = [
                Placeholder::make('current_photo_preview')
                    ->label('Current photo')
                    ->visible($photo !== null && $photo->isImage())
                    ->content(fn (): HtmlString => new HtmlString(
                        '<img src="'.e($photo?->previewUrl()).'" alt="Current student photo" class="h-28 w-24 rounded-xl object-cover ring-1 ring-gray-200 dark:ring-white/10" />'
                    )),
                self::documentUpload('photo', 'Student photo', true),
            ];

            foreach ([
                [DocumentType::Aadhaar, 'Aadhaar card'],
                [DocumentType::Marksheet, 'Marksheet'],
                [DocumentType::Signature, 'Signature'],
            ] as [$type, $label]) {
                $documentSchema[] = self::documentUpload($type->value, $label);
            }

            if ($hasIdCard) {
                $documentSchema[] = Toggle::make('regenerate_id_card')
                    ->label('Regenerate ID card with new photo')
                    ->helperText(CrmHint::field('regenerate_id_card'))
                    ->default(true);
            }

            $sections[] = Section::make('Photo & documents')
                ->description('Upload or replace files used on the dossier, admission, and ID card.')
                ->schema($documentSchema);
        }

        $sections = array_merge($sections, [
            Section::make('Personal Details')
                ->description('Complete the profile when you have more information from the student.')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('father_name')
                        ->label("Father's Name")
                        ->maxLength(255),
                    DatePicker::make('date_of_birth')
                        ->label('Date of Birth')
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
                        ->label('Alternate Mobile')
                        ->tel()
                        ->maxLength(10)
                        ->rule('nullable|regex:/^[6-9]\d{9}$/')
                        ->helperText('Optional second number. Must not match any other student\'s mobile or alternate number.'),
                ]),
            ...($hasActiveEnrollment ? [
                Section::make('Academic identity')
                    ->description('Roll number is issued when admission is approved.')
                    ->schema([
                        TextInput::make('enrollment_number')
                            ->label(StudentLabels::rollNumberLabel())
                            ->required()
                            ->maxLength(50)
                            ->helperText('Must be unique. ID card and receipts regenerate automatically if changed.')
                            ->extraInputAttributes(['class' => 'font-mono uppercase']),
                    ]),
            ] : []),
            Section::make('Address')
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
            Section::make('Category')
                ->schema([
                    Select::make('category')
                        ->options(self::categoryOptions())
                        ->native(false),
                ]),
            ...CustomFieldFormBuilder::sections(CustomFieldService::ENTITY_STUDENT),
        ]);

        return $sections;
    }

    protected static function documentUpload(string $name, string $label, bool $imageOnly = false): FileUpload
    {
        $upload = FileUpload::make($name)
            ->label($label)
            ->helperText(CrmHint::field($name === 'photo' ? 'student_photo' : 'student_documents'))
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
