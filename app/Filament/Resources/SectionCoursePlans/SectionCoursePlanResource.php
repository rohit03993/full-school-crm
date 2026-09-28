<?php

namespace App\Filament\Resources\SectionCoursePlans;

use App\Enums\BatchStaffRole;
use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Enums\SectionCoursePlanStatus;
use App\Enums\StandardCoursePracticalKind;
use App\Filament\Resources\SectionCoursePlans\Pages\EditSectionCoursePlan;
use App\Filament\Resources\SectionCoursePlans\Pages\ListSectionCoursePlans;
use App\Filament\Support\CrmTable;
use App\Models\BatchStaffAssignment;
use App\Models\SectionCoursePlan;
use App\Models\StandardCoursePlan;
use App\Support\CrmAccess;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use UnitEnum;

class SectionCoursePlanResource extends Resource
{
    protected static ?string $model = SectionCoursePlan::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?string $navigationLabel = 'Section plans';

    protected static ?string $modelLabel = 'Section plan';

    protected static ?string $pluralModelLabel = 'Section plans';

    protected static ?int $navigationSort = 25;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_ACADEMICS;

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::TeacherTracking)) {
            return false;
        }

        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if (CrmAccess::can($user, CrmPermission::AcademicsManage)) {
            return true;
        }

        return BatchStaffAssignment::query()
            ->where('user_id', $user->id)
            ->where('role', BatchStaffRole::SubjectTeacher)
            ->exists();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof SectionCoursePlan
            && CrmAccess::can(Auth::user(), CrmPermission::AcademicsManage)
            && $record->status === SectionCoursePlanStatus::Draft
            && (int) $record->version === 0;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Placeholder::make('review_note')
                    ->hiddenLabel()
                    ->visible(function (?SectionCoursePlan $record): bool {
                        return $record instanceof SectionCoursePlan
                            && $record->status === SectionCoursePlanStatus::SentBack
                            && filled($record->review_comment);
                    })
                    ->content(function (?SectionCoursePlan $record): HtmlString {
                        $comment = e((string) $record?->review_comment);

                        return new HtmlString(
                            '<div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100">'
                            .'<p class="font-semibold">The academic head sent this back</p>'
                            .'<p class="mt-1 whitespace-pre-line">'.$comment.'</p>'
                            .'</div>'
                        );
                    }),
                Section::make('Section')
                    ->description('This copy belongs to one section and one subject. The year and programme stay as they were on the course plan.')
                    ->icon(Heroicon::OutlinedAcademicCap)
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('section_name')
                            ->label('Section')
                            ->disabled()
                            ->dehydrated(false)
                            ->afterStateHydrated(function (TextInput $component): void {
                                $record = $component->getRecord();
                                $component->state($record instanceof SectionCoursePlan ? $record->batch?->selectLabel() : null);
                            }),
                        TextInput::make('subject_name')
                            ->label('Subject')
                            ->disabled()
                            ->dehydrated(false)
                            ->afterStateHydrated(function (TextInput $component): void {
                                $record = $component->getRecord();
                                $component->state($record instanceof SectionCoursePlan ? $record->courseSubject?->name : null);
                            }),
                        TextInput::make('faculty_name')
                            ->label('Subject teacher')
                            ->disabled()
                            ->dehydrated(false)
                            ->afterStateHydrated(function (TextInput $component): void {
                                $record = $component->getRecord();
                                $component->state($record instanceof SectionCoursePlan ? $record->subjectTeacherName() : null);
                            }),
                    ]),
                Section::make('Chapters and topics')
                    ->description('60 minutes is 1 day. The subject teacher can change days, the book, and the counts, then submit. Students never see the minutes.')
                    ->icon(Heroicon::OutlinedBookOpen)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('lecture_minutes')
                            ->label('Lecture duration')
                            ->helperText('This section starts with the course plan length. 60 minutes counts as 1 day.')
                            ->numeric()
                            ->required()
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

                                return $label;
                            })
                            ->columns(3)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Chapter')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                TextInput::make('estimated_marks')
                                    ->label('Estimated marks')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->nullable(),
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
                    ->description('Copied from the course plan. Days and marks can stay blank.')
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
                                    ->nullable(),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return CrmTable::configure($table)
            ->columns([
                TextColumn::make('section_label')
                    ->label('Section')
                    ->state(fn (SectionCoursePlan $record): string => $record->batch?->selectLabel() ?? '—'),
                TextColumn::make('courseSubject.name')
                    ->label('Subject')
                    ->searchable(),
                TextColumn::make('teacher')
                    ->label('Teacher')
                    ->state(fn (SectionCoursePlan $record): string => $record->subjectTeacherName()),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(function (SectionCoursePlanStatus|string|null $state): string {
                        $status = $state instanceof SectionCoursePlanStatus
                            ? $state
                            : SectionCoursePlanStatus::tryFrom((string) $state);

                        return $status?->label() ?? '—';
                    })
                    ->color(function (SectionCoursePlanStatus|string|null $state): string {
                        $status = $state instanceof SectionCoursePlanStatus
                            ? $state
                            : SectionCoursePlanStatus::tryFrom((string) $state);

                        return match ($status) {
                            SectionCoursePlanStatus::Submitted => 'info',
                            SectionCoursePlanStatus::SentBack => 'warning',
                            SectionCoursePlanStatus::Final => 'success',
                            default => 'gray',
                        };
                    })
                    ->description(fn (SectionCoursePlan $record): ?string => filled($record->review_comment)
                        ? (string) $record->review_comment
                        : null),
                TextColumn::make('version')
                    ->label('Version')
                    ->formatStateUsing(fn ($state): string => (int) $state > 0 ? (string) $state : '—'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with([
            'batch.staffAssignments.user',
            'batch.course',
            'batch.academicSession',
            'courseSubject',
        ]);

        $user = Auth::user();

        if ($user && ! CrmAccess::can($user, CrmPermission::AcademicsManage)) {
            $query->whereHas('batch.staffAssignments', function (Builder $assignments) use ($user): void {
                $assignments->where('batch_staff_assignments.user_id', $user->id)
                    ->where('batch_staff_assignments.role', BatchStaffRole::SubjectTeacher->value)
                    ->whereColumn('batch_staff_assignments.course_subject_id', 'section_course_plans.course_subject_id');
            });
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSectionCoursePlans::route('/'),
            'edit' => EditSectionCoursePlan::route('/{record}/edit'),
        ];
    }
}
