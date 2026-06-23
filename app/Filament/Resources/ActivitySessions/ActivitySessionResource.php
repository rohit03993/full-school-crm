<?php

namespace App\Filament\Resources\ActivitySessions;

use App\Enums\CrmPermission;
use App\Filament\Concerns\RequiresAnyCrmPermission;
use App\Filament\Forms\ActivitySessionFormSchema;
use App\Filament\Pages\ActivityAttendancePage;
use App\Filament\Resources\ActivitySessions\Pages\CreateActivitySession;
use App\Filament\Resources\ActivitySessions\Pages\EditActivitySession;
use App\Filament\Resources\ActivitySessions\Pages\ListActivitySessions;
use App\Filament\Support\CrmTable;
use App\Models\ActivitySession;
use App\Support\CrmNavigation;
use App\Support\CrmAccess;
use App\Support\EduExamLabels;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ActivitySessionResource extends Resource
{
    use RequiresAnyCrmPermission;

    /**
     * @return list<CrmPermission>
     */
    protected static function anyCrmPermissions(): array
    {
        return [
            CrmPermission::MarksImport,
        ];
    }
    protected static ?string $model = ActivitySession::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Tests & Exams';

    protected static ?string $modelLabel = 'Test / Exam';

    protected static ?string $pluralModelLabel = 'Tests & Exams';

    public static function getNavigationLabel(): string
    {
        return EduExamLabels::tests();
    }

    public static function getModelLabel(): string
    {
        return EduExamLabels::test();
    }

    public static function getPluralModelLabel(): string
    {
        return EduExamLabels::tests();
    }

    protected static ?int $navigationSort = 50;

    protected static string | UnitEnum | null $navigationGroup = CrmNavigation::GROUP_ACADEMICS;

    public static function canCreate(): bool
    {
        return CrmAccess::can(Auth::user(), CrmPermission::MarksImport);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Test / Exam')
                    ->description('For most tests, use Tests & Exams → Upload marks (Excel). Use this form only when entering one subject manually.')
                    ->schema(ActivitySessionFormSchema::fields())
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return CrmTable::configure($table)
            ->columns([
                TextColumn::make('activityType.name')->label('Type')->sortable(),
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('batch.name')->label('Batch')->searchable()->sortable(),
                TextColumn::make('session_date')->label('Date')->date('d M Y')->sortable(),
                TextColumn::make('present_count')->label('Present')->numeric(),
            ])
            ->defaultSort('session_date', 'desc')
            ->filters([
                SelectFilter::make('activity_type_id')
                    ->label('Type')
                    ->relationship('activityType', 'name'),
                SelectFilter::make('batch_id')
                    ->label('Batch')
                    ->relationship('batch', 'name'),
            ])
            ->recordActions([
                Action::make('markAttendance')
                    ->label(EduExamLabels::enterMarks())
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->url(fn (ActivitySession $record): string => ActivityAttendancePage::getUrl()
                        .'?id='.$record->id),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('activityType')->withCount([
            'activityAttendances as present_count' => fn ($query) => $query->where('is_present', true),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivitySessions::route('/'),
            'create' => CreateActivitySession::route('/create'),
            'edit' => EditActivitySession::route('/{record}/edit'),
        ];
    }
}
