<?php

namespace App\Filament\Resources\Enquiries;

use App\Enums\CrmPermission;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Enums\LeadSource;
use App\Enums\VisitStatus;
use App\Filament\Pages\StudentProfilePage;
use App\Filament\Resources\Enquiries\Pages\ListEnquiries;
use App\Filament\Support\CrmTable;
use App\Models\Enquiry;
use App\Models\User;
use App\Services\LeadAssignmentService;
use App\Support\CrmAccess;
use App\Support\CrmHint;
use App\Support\CrmNavigation;
use App\Support\MeetingForOptions;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class EnquiryResource extends Resource
{
    use RequiresCrmPermission;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::LeadsViewAll;
    }

    protected static ?string $model = Enquiry::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static ?string $navigationLabel = 'All Leads';

    protected static ?string $modelLabel = 'Lead';

    protected static ?string $pluralModelLabel = 'Leads';

    protected static ?string $recordTitleAttribute = 'enquiry_number';

    protected static ?int $navigationSort = 20;

    protected static string | UnitEnum | null $navigationGroup = CrmNavigation::GROUP_LEADS;

    public static function getNavigationTooltip(): ?string
    {
        return CrmHint::navigationTooltip('enquiries.list');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['student', 'course', 'meetingWith']);
    }

    public static function table(Table $table): Table
    {
        return CrmTable::configure($table)
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
                    ->label('Meeting for')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (?string $state): string => MeetingForOptions::label($state))
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
                    ->label('Assigned for calling')
                    ->placeholder('Not assigned')
                    ->description(fn (Enquiry $record): ?string => $record->calling_assigned_at
                        ? 'Assigned '.$record->calling_assigned_at->format('d M Y')
                        : null),
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->since()
                    ->sortable()
                    ->dateTimeTooltip('d M Y, h:i A'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('assigned_for_calling')
                    ->label('Assigned for calling')
                    ->placeholder('All leads')
                    ->trueLabel('Assigned')
                    ->falseLabel('Not assigned')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('calling_assigned_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('calling_assigned_at'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('student_enrolled')
                    ->label('Student type')
                    ->placeholder('All students')
                    ->trueLabel('Enrolled')
                    ->falseLabel('Prospect')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('student.activeEnrollment'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('student.activeEnrollment'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                SelectFilter::make('latest_visit_status')
                    ->label('Visit status')
                    ->options(collect(VisitStatus::cases())
                        ->mapWithKeys(fn (VisitStatus $status): array => [$status->value => $status->label()])
                        ->all()),
                SelectFilter::make('meeting_with_user_id')
                    ->label('Assigned staff')
                    ->options(fn (): array => LeadAssignmentService::activeStaffOptions())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $inner): Builder => $inner
                            ->where('meeting_with_user_id', $data['value'])
                            ->whereNotNull('calling_assigned_at'),
                    )),
                SelectFilter::make('lead_source')
                    ->label('Source')
                    ->options(collect(LeadSource::cases())
                        ->mapWithKeys(fn (LeadSource $source): array => [$source->value => $source->label()])
                        ->all()),
                SelectFilter::make('meeting_for')
                    ->label('Meeting for')
                    ->options(MeetingForOptions::formOptions()),
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
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulkAssignForCalling')
                        ->label('Assign for calling')
                        ->icon(Heroicon::OutlinedPhoneArrowUpRight)
                        ->visible(fn (): bool => CrmAccess::can(Auth::user(), CrmPermission::LeadsReassign))
                        ->form([
                            Select::make('staff_user_id')
                                ->label('Staff')
                                ->options(fn (): array => LeadAssignmentService::activeStaffOptions())
                                ->searchable()
                                ->required()
                                ->native(false),
                        ])
                        ->action(function (Collection $records, array $data, LeadAssignmentService $assignments): void {
                            $staff = User::query()->findOrFail($data['staff_user_id']);
                            $count = $assignments->assignManyForCalling($records, $staff, Auth::user());

                            Notification::make()
                                ->title('Leads assigned')
                                ->body("{$count} lead(s) assigned to {$staff->name} for calling.")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('bulkUnassignCalling')
                        ->label('Unassign from calling')
                        ->icon(Heroicon::OutlinedPhoneXMark)
                        ->color('gray')
                        ->visible(fn (): bool => CrmAccess::can(Auth::user(), CrmPermission::LeadsReassign))
                        ->requiresConfirmation()
                        ->modalHeading('Unassign selected leads')
                        ->modalDescription('Staff will no longer see these leads under Assigned to Call.')
                        ->action(function (Collection $records, LeadAssignmentService $assignments): void {
                            $count = $assignments->clearManyCallingAssignments($records);

                            Notification::make()
                                ->title('Leads unassigned')
                                ->body("{$count} lead(s) removed from calling queues.")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->recordActions([
                Action::make('assignForCalling')
                    ->label('Assign for calling')
                    ->icon(Heroicon::OutlinedPhoneArrowUpRight)
                    ->visible(fn (): bool => CrmAccess::can(Auth::user(), CrmPermission::LeadsReassign))
                    ->form([
                        Select::make('staff_user_id')
                            ->label('Staff')
                            ->options(fn (): array => LeadAssignmentService::activeStaffOptions())
                            ->default(fn (Enquiry $record): ?int => $record->meeting_with_user_id)
                            ->searchable()
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (Enquiry $record, array $data, LeadAssignmentService $assignments): void {
                        $staff = User::query()->findOrFail($data['staff_user_id']);
                        $assignments->assignForCalling($record, $staff, Auth::user());
                    }),
                Action::make('clearCallingAssignment')
                    ->label('Unassign')
                    ->icon(Heroicon::OutlinedPhoneXMark)
                    ->color('gray')
                    ->visible(fn (Enquiry $record): bool => CrmAccess::can(Auth::user(), CrmPermission::LeadsReassign)
                        && $record->calling_assigned_at !== null)
                    ->requiresConfirmation()
                    ->action(fn (Enquiry $record, LeadAssignmentService $assignments) => $assignments->clearCallingAssignment($record)),
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
