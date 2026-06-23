<?php

namespace App\Filament\Resources\Courses;

use App\Enums\CourseStatus;
use App\Enums\CrmPermission;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Support\CrmHint;
use App\Support\InstituteTerminology;
use App\Support\CrmNavigation;
use App\Enums\DurationType;
use App\Filament\Resources\Courses\Pages\CreateCourse;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Filament\Resources\Courses\Pages\ListCourses;
use App\Filament\Support\CrmTable;
use App\Models\Course;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CourseResource extends Resource
{
    use RequiresCrmPermission;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::AcademicsManage;
    }

    protected static ?string $model = Course::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $navigationLabel = 'Courses';

    protected static ?string $modelLabel = 'Course';

    protected static ?string $pluralModelLabel = 'Courses';

    public static function getModelLabel(): string
    {
        return InstituteTerminology::label('course');
    }

    public static function getPluralModelLabel(): string
    {
        return InstituteTerminology::label('course').'s';
    }

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 20;

    protected static string | UnitEnum | null $navigationGroup = CrmNavigation::GROUP_ACADEMICS;

    public static function getNavigationTooltip(): ?string
    {
        return CrmHint::navigationTooltip('courses.list');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Programme Details')
                    ->description(fn (): string => 'Create and manage '.strtolower(InstituteTerminology::label('course')).' entries for your institute.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Programme Name')
                            ->placeholder('e.g. Class 12 Science, JEE Foundation, B.Com Year 2')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('code')
                            ->label('Course Code')
                            ->placeholder('e.g. SCH-12-SCI, COACH-JEE-1Y, COL-BCOM-2Y')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->helperText(CrmHint::field('course_code')),
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
                        Toggle::make('show_on_website')
                            ->label('Show on public website')
                            ->helperText('When off, this programme stays in the CRM but is hidden from the homepage, courses page, and contact form.')
                            ->default(true),
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
        return CrmTable::configure($table)
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
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
                TextColumn::make('show_on_website')
                    ->label('Website')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Visible' : 'Hidden')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
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
