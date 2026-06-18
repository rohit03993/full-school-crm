<?php

namespace App\Filament\Resources\ActivitySessions;

use App\Filament\Forms\ActivitySessionFormSchema;
use App\Filament\Pages\ActivityAttendancePage;
use App\Filament\Resources\ActivitySessions\Pages\CreateActivitySession;
use App\Filament\Resources\ActivitySessions\Pages\EditActivitySession;
use App\Filament\Resources\ActivitySessions\Pages\ListActivitySessions;
use App\Models\ActivitySession;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivitySessionResource extends Resource
{
    protected static ?string $model = ActivitySession::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Activities';

    protected static ?string $modelLabel = 'Activity';

    protected static ?string $pluralModelLabel = 'Activities';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Activity Session')
                    ->description('Create a dated session for a batch, then mark student attendance.')
                    ->schema(ActivitySessionFormSchema::fields())
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
                    ->label('Mark Attendance')
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
