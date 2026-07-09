<?php

namespace App\Filament\Resources\Batches;

use App\Enums\BatchStaffRole;
use App\Enums\BatchShift;
use App\Enums\BatchStatus;
use App\Enums\CrmPermission;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Filament\Resources\Batches\Pages\CreateBatch;
use App\Filament\Resources\Batches\Pages\EditBatch;
use App\Filament\Resources\Batches\Pages\ListBatches;
use App\Filament\Support\CrmTable;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\CourseSubject;
use App\Support\ClassSectionLabel;
use App\Support\CrmHint;
use App\Support\CrmNavigation;
use App\Support\InstituteProfile;
use App\Support\InstituteTerminology;
use App\Support\StaffOptions;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class BatchResource extends Resource
{
    use RequiresCrmPermission;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::AcademicsManage;
    }

    protected static ?string $model = Batch::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Batches';

    protected static ?string $modelLabel = 'Batch';

    protected static ?string $pluralModelLabel = 'Batches';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 30;

    protected static string | UnitEnum | null $navigationGroup = CrmNavigation::GROUP_ACADEMICS;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationTooltip(): ?string
    {
        return CrmHint::navigationTooltip('batches.list');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Batch Details')
                    ->description('Class sections, coaching batches, or college groups — linked to a programme and academic session.')
                    ->schema([
                        Select::make('academic_session_id')
                            ->label('Academic Session')
                            ->relationship('academicSession', 'name')
                            ->getOptionLabelFromRecordUsing(fn (AcademicSession $record): string => $record->selectLabel())
                            ->default(fn (): ?int => AcademicSession::current()?->id)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                        TextInput::make('section')
                            ->label('Section')
                            ->placeholder('e.g. A, B, Morning')
                            ->required()
                            ->maxLength(50),
                        Select::make('course_id')
                            ->label('Programme')
                            ->options(fn (): array => InstituteProfile::activeCourseAdmissionOptions())
                            ->searchable()
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?int $state): void {
                                $rows = $state
                                    ? CourseSubject::query()
                                        ->where('course_id', $state)
                                        ->active()
                                        ->ordered()
                                        ->get()
                                        ->map(fn (CourseSubject $subject): array => [
                                            'course_subject_id' => $subject->id,
                                            'subject_name' => $subject->displayLabel(),
                                            'user_id' => null,
                                        ])
                                        ->values()
                                        ->all()
                                    : [];

                                $set('subject_teacher_assignments', $rows);
                            })
                            ->columnSpanFull(),
                        Select::make('status')
                            ->options(collect(BatchStatus::cases())->mapWithKeys(
                                fn (BatchStatus $status) => [$status->value => $status->label()],
                            ))
                            ->default(BatchStatus::Active->value)
                            ->required()
                            ->native(false),
                    ])
                    ->columns(2),
                Section::make('Staff assignments')
                    ->description('Optional. Assign a class/batch lead and subject teachers when you use structured academics and mark entry.')
                    ->schema([
                        Select::make('lead_teacher_user_id')
                            ->label('Class / batch lead teacher')
                            ->options(fn (): array => StaffOptions::facultyOptions())
                            ->searchable()
                            ->native(false)
                            ->placeholder('Not assigned'),
                        Repeater::make('subject_teacher_assignments')
                            ->label('Subject teachers')
                            ->schema([
                                Hidden::make('course_subject_id'),
                                TextInput::make('subject_name')
                                    ->label('Subject')
                                    ->disabled()
                                    ->dehydrated(false),
                                Select::make('user_id')
                                    ->label('Teacher')
                                    ->options(fn (): array => StaffOptions::facultyOptions())
                                    ->searchable()
                                    ->native(false)
                                    ->placeholder('Not assigned'),
                            ])
                            ->columns(2)
                            ->columnSpanFull()
                            ->defaultItems(0)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->visible(fn (Get $get): bool => filled($get('course_id')))
                            ->helperText(fn (Get $get): string => CourseSubject::query()
                                ->where('course_id', $get('course_id'))
                                ->active()
                                ->exists()
                                ? 'Assign a teacher per subject for this section. Leave blank to assign later.'
                                : 'Add subjects on the parent '.strtolower(InstituteTerminology::label('course')).' first — they appear here automatically.'),
                    ])
                    ->collapsed()
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return CrmTable::configure($table)
            ->columns([
                TextColumn::make('name')
                    ->label('Display')
                    ->state(fn (Batch $record): string => ClassSectionLabel::forBatch($record, includeSession: false))
                    ->searchable(query: function ($query, string $search): void {
                        $query->where(function ($inner) use ($search): void {
                            $inner->where('name', 'like', "%{$search}%")
                                ->orWhere('section', 'like', "%{$search}%")
                                ->orWhereHas('course', fn ($course) => $course->where('name', 'like', "%{$search}%"));
                        });
                    })
                    ->sortable(),
                TextColumn::make('internal_name')
                    ->label('Batch name')
                    ->state(fn (Batch $record): string => $record->name)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('section')
                    ->label('Section')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('shift')
                    ->label('Shift')
                    ->formatStateUsing(fn (?BatchShift $state): string => $state?->label() ?? '—')
                    ->toggleable(),
                TextColumn::make('academicSession.name')
                    ->label('Session')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('course.name')
                    ->label('Programme')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('trainer.name')
                    ->label('Faculty')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('lead_teacher_name')
                    ->label('Lead teacher')
                    ->state(function (Batch $record): string {
                        $record->loadMissing('staffAssignments.user');

                        $lead = $record->staffAssignments
                            ->first(fn ($row) => $row->role === BatchStaffRole::LeadTeacher);

                        return $lead?->user?->name ?? '—';
                    })
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('start_date')
                    ->label('Start')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label('End')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('active_students_count')
                    ->label('Students')
                    ->counts('activeStudents')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (BatchStatus $state): string => match ($state) {
                        BatchStatus::Active => 'success',
                        BatchStatus::Completed => 'gray',
                    })
                    ->formatStateUsing(fn (BatchStatus $state): string => $state->label()),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('academic_session_id')
                    ->label('Session')
                    ->relationship('academicSession', 'name'),
                SelectFilter::make('course_id')
                    ->label('Programme')
                    ->options(fn (): array => InstituteProfile::activeCourseOptions()),
                SelectFilter::make('status')
                    ->options(collect(BatchStatus::cases())->mapWithKeys(
                        fn (BatchStatus $status) => [$status->value => $status->label()],
                    )),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBatches::route('/'),
            'create' => CreateBatch::route('/create'),
            'edit' => EditBatch::route('/{record}/edit'),
        ];
    }
}
