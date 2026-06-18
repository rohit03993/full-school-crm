<?php

namespace App\Filament\Resources\Enquiries;

use App\Enums\LeadSource;
use App\Enums\MeetingFor;
use App\Enums\VisitStatus;
use App\Filament\Pages\StudentProfilePage;
use App\Filament\Resources\Enquiries\Pages\ListEnquiries;
use App\Models\Enquiry;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class EnquiryResource extends Resource
{
    protected static ?string $model = Enquiry::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static ?string $navigationLabel = 'All Leads';

    protected static ?string $modelLabel = 'Lead';

    protected static ?string $pluralModelLabel = 'Leads';

    protected static ?string $recordTitleAttribute = 'enquiry_number';

    protected static ?int $navigationSort = -95;

    protected static string | UnitEnum | null $navigationGroup = 'CRM';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['student', 'course', 'meetingWith']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('enquiry_number')
                    ->label('Enquiry No.')
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
                    ->description(fn (Enquiry $record): string => $record->student?->mobile ?? ''),
                TextColumn::make('course.name')
                    ->label('Course')
                    ->placeholder('Not decided')
                    ->limit(30)
                    ->toggleable(),
                TextColumn::make('lead_source')
                    ->label('Source')
                    ->badge()
                    ->color(fn (LeadSource $state): string => match ($state) {
                        LeadSource::Website => 'success',
                        LeadSource::WalkIn => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (LeadSource $state): string => $state->label()),
                TextColumn::make('meeting_for')
                    ->label('Came For')
                    ->badge()
                    ->color(fn (MeetingFor $state): string => match ($state) {
                        MeetingFor::School => 'warning',
                        MeetingFor::Coaching => 'purple',
                        MeetingFor::College => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (MeetingFor $state): string => $state->label())
                    ->description(fn (Enquiry $record): ?string => $record->lead_source?->value === 'walk_in'
                        ? 'Walk-in'
                        : ($record->lead_source?->value === 'website' ? 'Website' : null)),
                TextColumn::make('latest_visit_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?VisitStatus $state): string => match ($state) {
                        VisitStatus::Interested => 'success',
                        VisitStatus::FollowUpRequired => 'warning',
                        VisitStatus::AdmissionReady => 'info',
                        VisitStatus::NotInterested => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?VisitStatus $state): string => $state?->label() ?? '—'),
                TextColumn::make('meetingWith.name')
                    ->label('Staff')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->since()
                    ->sortable()
                    ->dateTimeTooltip('d M Y, h:i A'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('lead_source')
                    ->label('Source')
                    ->options([
                        LeadSource::Website->value => LeadSource::Website->label(),
                        LeadSource::WalkIn->value => LeadSource::WalkIn->label(),
                    ]),
                SelectFilter::make('meeting_for')
                    ->label('Came For')
                    ->options([
                        MeetingFor::School->value => MeetingFor::School->label(),
                        MeetingFor::Coaching->value => MeetingFor::Coaching->label(),
                        MeetingFor::College->value => MeetingFor::College->label(),
                    ]),
                Filter::make('created_at')
                    ->label('Date')
                    ->schema([
                        DatePicker::make('from')
                            ->label('From')
                            ->native(false),
                        DatePicker::make('until')
                            ->label('Until')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (filled($data['from'] ?? null)) {
                            $indicators[] = Indicator::make(
                                'From '.Carbon::parse($data['from'])->format('d M Y'),
                            )->removeField('from');
                        }

                        if (filled($data['until'] ?? null)) {
                            $indicators[] = Indicator::make(
                                'Until '.Carbon::parse($data['until'])->format('d M Y'),
                            )->removeField('until');
                        }

                        return $indicators;
                    }),
            ])
            ->recordUrl(
                fn (Enquiry $record): string => StudentProfilePage::getUrl(['record' => $record->student_id]),
            )
            ->emptyStateHeading('No leads yet')
            ->emptyStateDescription('Website and walk-in enquiries will appear here.')
            ->emptyStateIcon(Heroicon::OutlinedInbox);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEnquiries::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
