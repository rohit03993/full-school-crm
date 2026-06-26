<?php

namespace App\Filament\Resources\Batches;

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
use App\Support\CrmHint;
use App\Support\CrmNavigation;
use App\Support\InstituteProfile;
use App\Support\StaffOptions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
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
                            ->native(false),
                        TextInput::make('name')
                            ->label('Batch Name')
                            ->placeholder('e.g. Class 12-A, JEE Batch 2026, B.Com Sem 2-B')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('section')
                            ->label('Section')
                            ->placeholder('e.g. A, B, Morning')
                            ->maxLength(50),
                        Select::make('shift')
                            ->label('Shift')
                            ->options(collect(BatchShift::cases())->mapWithKeys(
                                fn (BatchShift $shift) => [$shift->value => $shift->label()],
                            ))
                            ->native(false),
                        Select::make('course_id')
                            ->label('Programme')
                            ->options(fn (): array => InstituteProfile::activeCourseAdmissionOptions())
                            ->searchable()
                            ->required()
                            ->native(false)
                            ->columnSpanFull(),
                        Select::make('trainer_user_id')
                            ->label('Faculty / Teacher')
                            ->options(fn (): array => StaffOptions::facultyOptions())
                            ->searchable()
                            ->required()
                            ->native(false),
                        DatePicker::make('start_date')
                            ->label('Start Date')
                            ->native(false),
                        DatePicker::make('end_date')
                            ->label('End Date')
                            ->afterOrEqual('start_date')
                            ->native(false),
                        Select::make('status')
                            ->options(collect(BatchStatus::cases())->mapWithKeys(
                                fn (BatchStatus $status) => [$status->value => $status->label()],
                            ))
                            ->default(BatchStatus::Active->value)
                            ->required()
                            ->native(false),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return CrmTable::configure($table)
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
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
                    ->searchable(),
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
