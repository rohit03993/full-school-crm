<?php

namespace App\Filament\Resources\Admissions;

use App\Enums\AdmissionStatus;
use App\Filament\Pages\StudentProfilePage;
use App\Filament\Resources\Admissions\Pages\ListAdmissions;
use App\Filament\Resources\Admissions\Pages\ViewAdmission;
use App\Models\Admission;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AdmissionResource extends Resource
{
    protected static ?string $model = Admission::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Admission Requests';

    protected static ?string $modelLabel = 'Admission Request';

    protected static ?string $pluralModelLabel = 'Admission Requests';

    protected static ?string $recordTitleAttribute = 'admission_number';

    protected static ?int $navigationSort = -90;

    protected static string | UnitEnum | null $navigationGroup = 'CRM';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()
            ->where('status', AdmissionStatus::VerificationPending)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['student', 'enquiry.course', 'documents']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('admission_number')
                    ->label('Admission No.')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->color('primary'),
                TextColumn::make('student.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Admission $record): string => $record->student?->mobile ?? ''),
                TextColumn::make('enquiry.course.name')
                    ->label('Course')
                    ->placeholder('—')
                    ->limit(35),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (AdmissionStatus $state): string => match ($state) {
                        AdmissionStatus::Submitted => 'gray',
                        AdmissionStatus::VerificationPending => 'warning',
                        AdmissionStatus::Approved => 'success',
                        AdmissionStatus::Rejected => 'danger',
                    })
                    ->formatStateUsing(fn (AdmissionStatus $state): string => $state->label()),
                TextColumn::make('net_fee')
                    ->label('Net Fee')
                    ->money('INR')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->since()
                    ->sortable()
                    ->placeholder('Not yet')
                    ->dateTimeTooltip('d M Y, h:i A'),
                TextColumn::make('created_at')
                    ->label('Converted')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->dateTimeTooltip('d M Y, h:i A'),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(AdmissionStatus::cases())->mapWithKeys(
                        fn (AdmissionStatus $status) => [$status->value => $status->label()],
                    ))
                    ->default(AdmissionStatus::VerificationPending->value),
            ])
            ->recordActions([
                Action::make('review')
                    ->label('Review')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (Admission $record): string => static::getUrl('view', ['record' => $record])),
                Action::make('studentProfile')
                    ->label('Student Profile')
                    ->icon(Heroicon::OutlinedUser)
                    ->url(fn (Admission $record): string => StudentProfilePage::getUrl([
                        'record' => $record->student_id,
                    ]).'?tab=admission'),
            ])
            ->recordUrl(
                fn (Admission $record): string => static::getUrl('view', ['record' => $record]),
            )
            ->emptyStateHeading('No admission requests yet')
            ->emptyStateDescription('Converted admissions awaiting form submission or verification will appear here.')
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentCheck);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdmissions::route('/'),
            'view' => ViewAdmission::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
