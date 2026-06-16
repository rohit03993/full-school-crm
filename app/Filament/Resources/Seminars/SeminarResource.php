<?php

namespace App\Filament\Resources\Seminars;

use App\Enums\ActivityKind;
use App\Filament\Forms\ActivitySessionFormSchema;
use App\Filament\Pages\ActivityAttendancePage;
use App\Filament\Resources\Seminars\Pages\CreateSeminar;
use App\Filament\Resources\Seminars\Pages\EditSeminar;
use App\Filament\Resources\Seminars\Pages\ListSeminars;
use App\Models\Seminar;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SeminarResource extends Resource
{
    protected static ?string $model = Seminar::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMicrophone;

    protected static ?string $navigationLabel = 'Seminars';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Seminar')
                    ->description('Title, date, and batch.')
                    ->schema(ActivitySessionFormSchema::fields(
                        titleField: 'title',
                        dateField: 'seminar_date',
                        titlePlaceholder: 'e.g. Career guidance with industry guest',
                    ))
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('batch.name')->label('Batch')->sortable(),
                TextColumn::make('seminar_date')->label('Date')->date('d M Y')->sortable(),
                TextColumn::make('present_count')->label('Present')->numeric(),
            ])
            ->defaultSort('seminar_date', 'desc')
            ->filters([
                SelectFilter::make('batch_id')->label('Batch')->relationship('batch', 'name'),
            ])
            ->recordActions([
                Action::make('markAttendance')
                    ->label('Mark Attendance')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->url(fn (Seminar $record): string => ActivityAttendancePage::getUrl()
                        .'?kind='.ActivityKind::Seminar->value.'&id='.$record->id),
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
            'index' => ListSeminars::route('/'),
            'create' => CreateSeminar::route('/create'),
            'edit' => EditSeminar::route('/{record}/edit'),
        ];
    }
}
