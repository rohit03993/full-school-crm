<?php

namespace App\Filament\Resources\StandardCoursePlans;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Enums\StandardCoursePlanStatus;
use App\Enums\StandardCoursePracticalKind;
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
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
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
            ->columns(1)
            ->components([
                Section::make('Programme')
                    ->description('One plan is for one year, one programme, and one subject.')
                    ->icon(Heroicon::OutlinedAcademicCap)
                    ->columns(3)
                    ->columnSpanFull()
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
                    ->description('Set the lecture length once for this subject. 60 minutes is 1 day. Each topic then shows how many days it needs. DPP, Quiz/PYQs, and test are counts.')
                    ->icon(Heroicon::OutlinedBookOpen)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('lecture_minutes')
                            ->label('Lecture duration')
                            ->helperText('Asked once for this subject. 60 minutes counts as 1 day. A topic of 160 minutes then shows as 3 days.')
                            ->numeric()
                            ->required()
                            ->default(60)
                            ->minValue(1)
                            ->maxValue(300)
                            ->suffix('min')
                            ->live()
                            ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                                $lecture = max(1, (int) $state);

                                foreach ($get('chapters') ?? [] as $chapterKey => $chapter) {
                                    if (! is_array($chapter)) {
                                        continue;
                                    }

                                    foreach ($chapter['topics'] ?? [] as $topicKey => $topic) {
                                        if (! is_array($topic)) {
                                            continue;
                                        }

                                        $minutes = (int) ($topic['planned_minutes'] ?? 0);

                                        if ($minutes > 0) {
                                            $set("chapters.{$chapterKey}.topics.{$topicKey}.planned_days", StandardCoursePlan::teachingDays($minutes, $lecture));
                                        }
                                    }
                                }
                            }),
                        Placeholder::make('plan_teaching_days')
                            ->label('Total teaching days')
                            ->content(function (Get $get): string {
                                $lecture = max(1, (int) ($get('lecture_minutes') ?: 60));
                                $minutes = 0;

                                foreach ($get('chapters') ?? [] as $chapter) {
                                    if (! is_array($chapter)) {
                                        continue;
                                    }

                                    foreach ($chapter['topics'] ?? [] as $topic) {
                                        if (is_array($topic)) {
                                            $minutes += (int) ($topic['planned_minutes'] ?? 0);
                                        }
                                    }
                                }

                                $days = StandardCoursePlan::teachingDays($minutes, $lecture);

                                return $days.' '.($days === 1 ? 'day' : 'days');
                            }),
                        Repeater::make('chapters')
                            ->hiddenLabel()
                            ->relationship()
                            ->orderColumn('sort_order')
                            ->collapsible()
                            ->addActionLabel('Add chapter')
                            ->extraAttributes(['class' => 'course-plan-chapters'])
                            ->itemLabel(function (array $state, Get $get): string {
                                $name = filled($state['name'] ?? null) ? (string) $state['name'] : 'New chapter';
                                $lecture = max(1, (int) ($get('/data.lecture_minutes') ?: 60));
                                $minutes = 0;

                                foreach ($state['topics'] ?? [] as $topic) {
                                    if (is_array($topic)) {
                                        $minutes += (int) ($topic['planned_minutes'] ?? 0);
                                    }
                                }

                                $label = $name;

                                if ($minutes > 0) {
                                    $days = StandardCoursePlan::teachingDays($minutes, $lecture);
                                    $label .= ' · '.$days.' '.($days === 1 ? 'day' : 'days');
                                }

                                if (filled($state['estimated_marks'] ?? null)) {
                                    $label .= ' · '.$state['estimated_marks'].' marks';
                                }

                                return $label;
                            })
                            ->columns(3)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Chapter')
                                    ->placeholder('Electrostatics')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                TextInput::make('estimated_marks')
                                    ->label('Estimated marks')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->nullable()
                                    ->placeholder('Optional')
                                    ->helperText('Leave blank when this chapter has no mark estimate.'),
                                Repeater::make('topics')
                                    ->label('Topics')
                                    ->relationship()
                                    ->orderColumn('sort_order')
                                    ->compact()
                                    ->addActionLabel('Add topic')
                                    ->table([
                                        TableColumn::make('Topic')->markAsRequired()->width('26%'),
                                        TableColumn::make('Days')->markAsRequired()->width('12%'),
                                        TableColumn::make('Reference book')->width('22%'),
                                        TableColumn::make('DPP')->width('12%'),
                                        TableColumn::make('Quiz/PYQs')->width('16%'),
                                        TableColumn::make('Test')->width('12%'),
                                    ])
                                    ->schema([
                                        TextInput::make('name')
                                            ->hiddenLabel()
                                            ->placeholder('Coulomb\'s Law')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('planned_days')
                                            ->hiddenLabel()
                                            ->numeric()
                                            ->required()
                                            ->minValue(1)
                                            ->suffix('days')
                                            ->dehydrated(false)
                                            ->live()
                                            ->afterStateHydrated(function (TextInput $component, Get $get): void {
                                                $minutes = (int) $get('planned_minutes');
                                                $lecture = max(1, (int) ($get('/data.lecture_minutes') ?: 60));
                                                $component->state($minutes > 0 ? StandardCoursePlan::teachingDays($minutes, $lecture) : null);
                                            })
                                            ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                                $lecture = max(1, (int) ($get('/data.lecture_minutes') ?: 60));
                                                $set('planned_minutes', max(1, (int) $state) * $lecture);
                                            }),
                                        Hidden::make('planned_minutes'),
                                        TextInput::make('reference_book')
                                            ->hiddenLabel()
                                            ->placeholder('HC Verma')
                                            ->maxLength(255),
                                        TextInput::make('dpp_count')
                                            ->hiddenLabel()
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(0)
                                            ->required(),
                                        TextInput::make('quiz_count')
                                            ->hiddenLabel()
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(0)
                                            ->required(),
                                        TextInput::make('test_count')
                                            ->hiddenLabel()
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(0)
                                            ->required(),
                                    ])
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ]),
                Section::make('Practicals')
                    ->description('Schools can list experiments and activities. Days and marks can stay blank. A plan can still be marked Ready from chapters and topics alone.')
                    ->icon(Heroicon::OutlinedBeaker)
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('practicals')
                            ->hiddenLabel()
                            ->relationship()
                            ->orderColumn('sort_order')
                            ->defaultItems(0)
                            ->compact()
                            ->addActionLabel('Add practical')
                            ->table([
                                TableColumn::make('Practical')->markAsRequired()->width('46%'),
                                TableColumn::make('Type')->width('18%'),
                                TableColumn::make('Days')->width('18%'),
                                TableColumn::make('Marks')->width('18%'),
                            ])
                            ->schema([
                                TextInput::make('name')
                                    ->hiddenLabel()
                                    ->placeholder('Young\'s modulus of a wire')
                                    ->required()
                                    ->maxLength(255),
                                Select::make('kind')
                                    ->hiddenLabel()
                                    ->options(collect(StandardCoursePracticalKind::cases())->mapWithKeys(
                                        fn (StandardCoursePracticalKind $kind): array => [$kind->value => $kind->label()],
                                    ))
                                    ->default(StandardCoursePracticalKind::Experiment->value)
                                    ->required()
                                    ->native(false),
                                TextInput::make('planned_days')
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->minValue(1)
                                    ->nullable()
                                    ->suffix('days')
                                    ->placeholder('Optional')
                                    ->dehydrated(false)
                                    ->live()
                                    ->afterStateHydrated(function (TextInput $component, Get $get): void {
                                        $minutes = (int) $get('planned_minutes');
                                        $lecture = max(1, (int) ($get('/data.lecture_minutes') ?: 60));
                                        $component->state($minutes > 0 ? StandardCoursePlan::teachingDays($minutes, $lecture) : null);
                                    })
                                    ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                        if (blank($state)) {
                                            $set('planned_minutes', null);

                                            return;
                                        }

                                        $lecture = max(1, (int) ($get('/data.lecture_minutes') ?: 60));
                                        $set('planned_minutes', max(1, (int) $state) * $lecture);
                                    }),
                                Hidden::make('planned_minutes'),
                                TextInput::make('estimated_marks')
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->nullable()
                                    ->placeholder('Optional'),
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
                TextColumn::make('practicals_count')
                    ->counts('practicals')
                    ->label('Practicals'),
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
