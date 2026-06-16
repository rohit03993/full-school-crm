<?php

namespace App\Filament\Resources\Batches;

use App\Enums\BatchStatus;
use App\Enums\RoleName;
use App\Filament\Resources\Batches\Pages\CreateBatch;
use App\Filament\Resources\Batches\Pages\EditBatch;
use App\Filament\Resources\Batches\Pages\ListBatches;
use App\Models\Batch;
use App\Models\Course;
use App\Models\User;
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

class BatchResource extends Resource
{
    protected static ?string $model = Batch::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Batches';

    protected static ?string $modelLabel = 'Batch';

    protected static ?string $pluralModelLabel = 'Batches';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Batch Details')
                    ->schema([
                        TextInput::make('name')
                            ->label('Batch Name')
                            ->placeholder('e.g. Front Office Batch A')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Select::make('course_id')
                            ->label('Course')
                            ->relationship('course', 'name')
                            ->getOptionLabelFromRecordUsing(fn (Course $record): string => $record->admissionSelectLabel())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                        Select::make('trainer_user_id')
                            ->label('Trainer')
                            ->options(fn (): array => User::query()
                                ->where('is_active', true)
                                ->whereHas('roles', fn ($query) => $query->whereIn('name', [
                                    RoleName::Staff->value,
                                    RoleName::SuperAdmin->value,
                                ]))
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->required()
                            ->native(false),
                        DatePicker::make('start_date')
                            ->label('Start Date')
                            ->required()
                            ->native(false),
                        DatePicker::make('end_date')
                            ->label('End Date')
                            ->required()
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
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('course.name')
                    ->label('Course')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('trainer.name')
                    ->label('Trainer')
                    ->searchable(),
                TextColumn::make('start_date')
                    ->label('Start')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label('End')
                    ->date('d M Y')
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
                SelectFilter::make('course_id')
                    ->label('Course')
                    ->relationship('course', 'name'),
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
