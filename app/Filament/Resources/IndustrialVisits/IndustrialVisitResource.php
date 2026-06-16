<?php

namespace App\Filament\Resources\IndustrialVisits;

use App\Enums\ActivityKind;
use App\Filament\Forms\ActivitySessionFormSchema;
use App\Filament\Pages\ActivityAttendancePage;
use App\Filament\Resources\IndustrialVisits\Pages\CreateIndustrialVisit;
use App\Filament\Resources\IndustrialVisits\Pages\EditIndustrialVisit;
use App\Filament\Resources\IndustrialVisits\Pages\ListIndustrialVisits;
use App\Models\IndustrialVisit;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IndustrialVisitResource extends Resource
{
    protected static ?string $model = IndustrialVisit::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Industrial Visits';

    protected static ?string $modelLabel = 'Industrial Visit';

    protected static ?string $pluralModelLabel = 'Industrial Visits';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Industrial Visit')
                    ->description('Title, date, and batch.')
                    ->schema(ActivitySessionFormSchema::fields(
                        titleField: 'name',
                        dateField: 'visit_date',
                        titlePlaceholder: 'e.g. Hotel Taj industrial visit',
                    ))
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Title')->searchable()->sortable(),
                TextColumn::make('batch.name')->label('Batch')->sortable(),
                TextColumn::make('visit_date')->label('Date')->date('d M Y')->sortable(),
                TextColumn::make('present_count')->label('Present')->numeric(),
            ])
            ->defaultSort('visit_date', 'desc')
            ->filters([
                SelectFilter::make('batch_id')->label('Batch')->relationship('batch', 'name'),
            ])
            ->recordActions([
                Action::make('markAttendance')
                    ->label('Mark Attendance')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->url(fn (IndustrialVisit $record): string => ActivityAttendancePage::getUrl()
                        .'?kind='.ActivityKind::IndustrialVisit->value.'&id='.$record->id),
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
            'index' => ListIndustrialVisits::route('/'),
            'create' => CreateIndustrialVisit::route('/create'),
            'edit' => EditIndustrialVisit::route('/{record}/edit'),
        ];
    }
}
