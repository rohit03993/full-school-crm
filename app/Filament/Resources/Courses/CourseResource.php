<?php

namespace App\Filament\Resources\Courses;

use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\DurationType;
use App\Filament\Resources\Courses\Pages\CreateCourse;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Filament\Resources\Courses\Pages\ListCourses;
use App\Models\Course;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CourseResource extends Resource
{
    protected static ?string $model = Course::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $navigationLabel = 'Courses';

    protected static ?string $modelLabel = 'Course';

    protected static ?string $pluralModelLabel = 'Courses';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Course Details')
                    ->description('Create any course — BSc, Diploma, Certificate, or fully custom.')
                    ->schema([
                        Select::make('course_type')
                            ->label('Course Type')
                            ->options(collect(CourseType::cases())->mapWithKeys(
                                fn (CourseType $type) => [$type->value => $type->label()],
                            ))
                            ->default(CourseType::Custom->value)
                            ->required()
                            ->native(false)
                            ->live(),
                        TextInput::make('name')
                            ->label('Course Name')
                            ->placeholder('e.g. Class 12 Science')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('code')
                            ->label('Course Code')
                            ->placeholder('e.g. BSC-HM-2Y')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->helperText('Short unique code used in reports and enquiries.'),
                        TextInput::make('duration')
                            ->label('Duration')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(120)
                            ->default(1),
                        Select::make('duration_type')
                            ->label('Duration Unit')
                            ->options(collect(DurationType::cases())->mapWithKeys(
                                fn (DurationType $type) => [$type->value => $type->label()],
                            ))
                            ->default(DurationType::Years->value)
                            ->required()
                            ->native(false),
                        TextInput::make('fee')
                            ->label('Course Fee')
                            ->numeric()
                            ->prefix('₹')
                            ->required()
                            ->minValue(0)
                            ->step(0.01)
                            ->default(0),
                        Select::make('status')
                            ->options(collect(CourseStatus::cases())->mapWithKeys(
                                fn (CourseStatus $status) => [$status->value => $status->label()],
                            ))
                            ->default(CourseStatus::Active->value)
                            ->required()
                            ->native(false),
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(4)
                            ->columnSpanFull(),
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
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('course_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (CourseType $state): string => $state->label()),
                TextColumn::make('duration_label')
                    ->label('Duration'),
                TextColumn::make('fee')
                    ->label('Fee')
                    ->money('INR')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (CourseStatus $state): string => match ($state) {
                        CourseStatus::Active => 'success',
                        CourseStatus::Inactive => 'gray',
                    })
                    ->formatStateUsing(fn (CourseStatus $state): string => $state->label()),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('course_type')
                    ->label('Type')
                    ->options(collect(CourseType::cases())->mapWithKeys(
                        fn (CourseType $type) => [$type->value => $type->label()],
                    )),
                SelectFilter::make('status')
                    ->options(collect(CourseStatus::cases())->mapWithKeys(
                        fn (CourseStatus $status) => [$status->value => $status->label()],
                    )),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCourses::route('/'),
            'create' => CreateCourse::route('/create'),
            'edit' => EditCourse::route('/{record}/edit'),
        ];
    }
}
