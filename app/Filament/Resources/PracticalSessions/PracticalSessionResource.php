<?php

namespace App\Filament\Resources\PracticalSessions;

use App\Enums\ActivityKind;
use App\Filament\Forms\ActivitySessionFormSchema;
use App\Filament\Pages\ActivityAttendancePage;
use App\Filament\Resources\PracticalSessions\Pages\CreatePracticalSession;
use App\Filament\Resources\PracticalSessions\Pages\EditPracticalSession;
use App\Filament\Resources\PracticalSessions\Pages\ListPracticalSessions;
use App\Models\PracticalSession;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PracticalSessionResource extends Resource
{
    protected static ?string $model = PracticalSession::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $navigationLabel = 'Practicals';

    protected static ?string $modelLabel = 'Practical Session';

    protected static ?string $pluralModelLabel = 'Practicals';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Practical Session')
                    ->description('Title, date, and batch — same layout as Industrial Visits and Seminars.')
                    ->schema(ActivitySessionFormSchema::fields(
                        titleField: 'title',
                        dateField: 'session_date',
                        titlePlaceholder: 'e.g. Food Production — knife skills',
                    ))
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('batch.name')->label('Batch')->searchable()->sortable(),
                TextColumn::make('session_date')->label('Date')->date('d M Y')->sortable(),
                TextColumn::make('present_count')->label('Present')->numeric(),
            ])
            ->defaultSort('session_date', 'desc')
            ->filters([
                SelectFilter::make('batch_id')
                    ->label('Batch')
                    ->relationship('batch', 'name'),
            ])
            ->recordActions([
                Action::make('markAttendance')
                    ->label('Mark Attendance')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->url(fn (PracticalSession $record): string => ActivityAttendancePage::getUrl()
                        .'?kind='.ActivityKind::Practical->value.'&id='.$record->id),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount([
            'activityAttendances as present_count' => fn ($query) => $query->where('is_present', true),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPracticalSessions::route('/'),
            'create' => CreatePracticalSession::route('/create'),
            'edit' => EditPracticalSession::route('/{record}/edit'),
        ];
    }
}
