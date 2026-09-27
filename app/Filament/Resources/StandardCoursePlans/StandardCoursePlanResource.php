<?php

namespace App\Filament\Resources\StandardCoursePlans;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Enums\StandardCoursePlanStatus;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Filament\Resources\StandardCoursePlans\Pages\CreateStandardCoursePlan;
use App\Filament\Resources\StandardCoursePlans\Pages\EditStandardCoursePlan;
use App\Filament\Resources\StandardCoursePlans\Pages\ListStandardCoursePlans;
use App\Filament\Support\CrmTable;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseSubject;
use App\Models\StandardCoursePlan;
use App\Support\CrmNavigation;
use Filament\Actions\EditAction;
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
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class StandardCoursePlanResource extends Resource
{
    use RequiresCrmPermission;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::AcademicsManage;
    }

    protected static function requiredLicenseFeature(): ?LicenseFeature
    {
        return LicenseFeature::TeacherTracking;
    }

    protected static ?string $model = StandardCoursePlan::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Course planner';

    protected static ?string $modelLabel = 'Course plan';

    protected static ?string $pluralModelLabel = 'Course plans';

    protected static ?int $navigationSort = 24;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_ACADEMICS;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Programme')
                    ->description('One plan is for one year, one programme, and one subject.')
                    ->columns(3)
                    ->schema([
                        Select::make('academic_session_id')
                            ->label('Year')
                            ->options(fn (): array => AcademicSession::query()
                                ->where('is_active', true)
                                ->orderByDesc('is_current')
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (AcademicSession $session): array => [$session->id => $session->selectLabel()])
                                ->all())
                            ->default(fn (): ?int => AcademicSession::current()?->id)
                            ->searchable()
                            ->required()
                            ->live(),
                        Select::make('course_id')
                            ->label('Programme')
                            ->options(fn (): array => Course::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('course_subject_id', null)),
                        Select::make('course_subject_id')
                            ->label('Subject')
                            ->options(fn (Get $get): array => CourseSubject::query()
                                ->where('course_id', $get('course_id'))
                                ->active()
                                ->ordered()
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->required()
                            ->disabled(fn (Get $get): bool => blank($get('course_id'))),
                        Hidden::make('status')
                            ->default(StandardCoursePlanStatus::Draft->value),
                    ]),
                Section::make('Chapters and topics')
                    ->description('Planned minutes are the teaching time for that topic. DPP, quiz, and test are counts.')
                    ->schema([
                        Repeater::make('chapters')
                            ->relationship()
                            ->orderColumn('sort_order')
                            ->addActionLabel('Add chapter')
                            ->itemLabel(fn (array $state): ?string => filled($state['name'] ?? null) ? $state['name'] : 'Chapter')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Chapter')
                                    ->placeholder('Electrostatics')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                Repeater::make('topics')
                                    ->relationship()
                                    ->orderColumn('sort_order')
                                    ->addActionLabel('Add topic')
                                    ->itemLabel(fn (array $state): ?string => filled($state['name'] ?? null) ? $state['name'] : 'Topic')
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Topic')
                                            ->placeholder('Coulomb\'s Law')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                        TextInput::make('planned_minutes')
                                            ->label('Planned minutes')
                                            ->numeric()
                                            ->required()
                                            ->minValue(1)
                                            ->suffix('min'),
                                        TextInput::make('reference_book')
                                            ->label('Reference book')
                                            ->placeholder('HC Verma')
                                            ->maxLength(255),
                                        TextInput::make('dpp_count')
                                            ->label('DPP')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(0)
                                            ->required(),
                                        TextInput::make('quiz_count')
                                            ->label('Quiz')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(0)
                                            ->required(),
                                        TextInput::make('test_count')
                                            ->label('Test')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(0)
                                            ->required(),
                                    ])
                                    ->columns(2)
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return CrmTable::configure($table)
            ->columns([
                TextColumn::make('academicSession.name')
                    ->label('Year')
                    ->sortable(),
                TextColumn::make('course.name')
                    ->label('Programme')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('courseSubject.name')
                    ->label('Subject')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('chapters_count')
                    ->counts('chapters')
                    ->label('Chapters'),
                TextColumn::make('topics_count')
                    ->counts('topics')
                    ->label('Topics'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (StandardCoursePlanStatus $state): string => $state->label()),
            ])
            ->filters([
                SelectFilter::make('academic_session_id')
                    ->label('Year')
                    ->relationship('academicSession', 'name'),
                SelectFilter::make('course_id')
                    ->label('Programme')
                    ->relationship('course', 'name'),
                SelectFilter::make('status')
                    ->options(collect(StandardCoursePlanStatus::cases())->mapWithKeys(
                        fn (StandardCoursePlanStatus $status): array => [$status->value => $status->label()],
                    )),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['academicSession', 'course', 'courseSubject']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStandardCoursePlans::route('/'),
            'create' => CreateStandardCoursePlan::route('/create'),
            'edit' => EditStandardCoursePlan::route('/{record}/edit'),
        ];
    }
}
