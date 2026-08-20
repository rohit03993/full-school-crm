<?php

namespace App\Filament\Resources\Students;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Filament\Pages\StudentProfilePage;
use App\Filament\Pages\StudentSearchPage;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Support\CrmTable;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Student;
use App\Services\BatchService;
use App\Services\FaceVerify\FaceVerifyGateService;
use App\Services\FeesDashboardService;
use App\Services\StudentProfileDeleteService;
use App\Support\BatchSelectOptions;
use App\Support\ClassSectionLabel;
use App\Support\CrmAccess;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use App\Support\InstituteTerminology;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Throwable;
use UnitEnum;

class StudentResource extends Resource
{
    use RequiresCrmPermission;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::StudentsView;
    }

    protected static ?string $model = Student::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = 'Student';

    protected static ?string $pluralModelLabel = 'Students';

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::students();
    }

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 10;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_STUDENTS;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->inStudentsDirectory()
            ->with([
                'activeEnrollment.course',
                'activeEnrollment.academicSession',
                'activeEnrollment.feeStructure.installments',
                'activeBatchStudent.batch.course',
            ]);
    }

    public static function table(Table $table): Table
    {
        return CrmTable::configure($table)
            ->deferFilters(false)
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns([
                'default' => 1,
                'md' => 2,
                'xl' => 3,
            ])
            ->recordClasses(fn (Student $record): ?string => blank($record->mobile)
                ? 'bg-danger-50/60 dark:bg-danger-500/5'
                : null)
            ->columns([
                TextColumn::make('activeEnrollment.enrollment_number')
                    ->label('Roll No.')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->placeholder('—'),
                TextColumn::make('name')
                    ->label('Student')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Student $record): string => $record->father_name ?? ''),
                TextColumn::make('mobile')
                    ->label('Mobile')
                    ->searchable()
                    ->fontFamily('mono')
                    ->copyable(fn (?string $state): bool => filled($state))
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? $state : 'Missing — add from profile')
                    ->description(fn (Student $record): ?string => blank($record->mobile) ? $record->mobile_import_note : null)
                    ->badge(fn (?string $state): bool => blank($state))
                    ->color(fn (?string $state): string => blank($state) ? 'danger' : 'gray'),
                TextColumn::make('activeEnrollment.course.name')
                    ->label(InstituteTerminology::label('course'))
                    ->placeholder('—')
                    ->limit(30)
                    ->toggleable(),
                TextColumn::make('activeEnrollment.feeStructure.pending_amount')
                    ->label('Fee pending')
                    ->money('INR')
                    ->placeholder('—')
                    ->sortable()
                    ->color(fn ($state): string => (float) ($state ?? 0) > 0 ? 'warning' : 'success')
                    ->hidden(fn (): bool => ! FeatureGate::enabled(LicenseFeature::Fees) || ! CrmAccess::canViewFees(Auth::user())),
                TextColumn::make('fee_next_due')
                    ->label('Next due')
                    ->state(function (Student $record): ?string {
                        $date = app(FeesDashboardService::class)->nextDueDateForStudent($record);

                        return $date?->format('d M Y');
                    })
                    ->placeholder('—')
                    ->toggleable()
                    ->hiddenFrom('md')
                    ->hidden(fn (): bool => ! FeatureGate::enabled(LicenseFeature::Fees) || ! CrmAccess::canViewFees(Auth::user())),
                TextColumn::make('fee_status')
                    ->label('Fee status')
                    ->badge()
                    ->state(function (Student $record): ?string {
                        return app(FeesDashboardService::class)->feeStatusForStudent($record)['label'] ?? null;
                    })
                    ->color(function (Student $record): string {
                        return app(FeesDashboardService::class)->feeStatusForStudent($record)['color'] ?? 'gray';
                    })
                    ->placeholder('—')
                    ->toggleable()
                    ->hiddenFrom('md')
                    ->hidden(fn (): bool => ! FeatureGate::enabled(LicenseFeature::Fees) || ! CrmAccess::canViewFees(Auth::user())),
                TextColumn::make('activeEnrollment.academicSession.name')
                    ->label('Session')
                    ->placeholder('—')
                    ->toggleable()
                    ->hiddenFrom('md'),
                TextColumn::make('activeBatchStudent.batch.name')
                    ->label(InstituteTerminology::label('batch'))
                    ->state(function (Student $record): ?string {
                        $batch = $record->activeBatchStudent?->batch;

                        return $batch
                            ? ClassSectionLabel::forBatch($batch, includeSession: false, includeShift: false)
                            : null;
                    })
                    ->placeholder('No section')
                    ->toggleable()
                    ->hiddenFrom('md'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (StudentStatus $state): string => match ($state) {
                        StudentStatus::Enrolled => 'success',
                        StudentStatus::Enquiry => 'gray',
                        StudentStatus::Completed => 'info',
                        StudentStatus::Dropped => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (StudentStatus $state): string => $state->label()),
                TextColumn::make('activeEnrollment.enrolled_at')
                    ->label('Enrolled')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('missing_mobile')
                    ->label('Mobile number')
                    ->placeholder('All students')
                    ->trueLabel('Missing mobile / import issue')
                    ->falseLabel('Has mobile')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNull('mobile'),
                        false: fn (Builder $query): Builder => $query->whereNotNull('mobile'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                SelectFilter::make('status')
                    ->options(collect([
                        StudentStatus::Enrolled,
                        StudentStatus::Completed,
                        StudentStatus::Dropped,
                    ])->mapWithKeys(
                        fn (StudentStatus $status) => [$status->value => $status->label()],
                    )),
                SelectFilter::make('section')
                    ->label('Class & section')
                    ->options(fn (): array => BatchSelectOptions::activeOptions())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query): Builder => $query->whereHas(
                            'activeBatchStudent',
                            fn (Builder $query): Builder => $query->where('batch_id', $data['value']),
                        ),
                    )),
                TernaryFilter::make('section_assigned')
                    ->label('Section assigned')
                    ->placeholder('All students')
                    ->trueLabel('Has class & section')
                    ->falseLabel('No section assigned')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('activeBatchStudent'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('activeBatchStudent'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                SelectFilter::make('academic_session')
                    ->label('Session')
                    ->options(fn (): array => AcademicSession::query()
                        ->where('is_active', true)
                        ->orderByDesc('starts_on')
                        ->pluck('name', 'id')
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query): Builder => $query->whereHas(
                            'activeEnrollment',
                            fn (Builder $query): Builder => $query->where('academic_session_id', $data['value']),
                        ),
                    )),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('assignSection')
                        ->label('Assign class & section')
                        ->icon(Heroicon::OutlinedRectangleStack)
                        ->visible(fn (): bool => CrmAccess::can(Auth::user(), CrmPermission::StudentsEdit))
                        ->form([
                            Select::make('batch_id')
                                ->label('Class & section')
                                ->options(fn (): array => BatchSelectOptions::activeOptions())
                                ->searchable()
                                ->required()
                                ->native(false)
                                ->helperText('Only students in the same class and session as this section are updated. Others are skipped.'),
                        ])
                        ->action(function (Collection $records, array $data, BatchService $batches): void {
                            $batch = Batch::query()->findOrFail((int) $data['batch_id']);
                            $result = $batches->bulkAssignSkippingMismatches(
                                $batch,
                                $records->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                                Auth::user(),
                            );

                            $label = ClassSectionLabel::forBatch($batch, includeSession: false, includeShift: false);
                            $body = $result['assigned'].' student(s) assigned to '.$label.'.';

                            if ($result['skipped'] > 0) {
                                $body .= ' '.$result['skipped'].' skipped (different class/session, or no enrollment).';
                            }

                            Notification::make()
                                ->title('Section assignment')
                                ->body($body)
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('deleteWithoutHistory')
                        ->label('Delete (no fees or attendance)')
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->visible(fn (): bool => Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false)
                        ->requiresConfirmation()
                        ->modalHeading('Permanently delete selected students?')
                        ->modalDescription('Students with fee payments, penalties, or attendance are skipped so that data is not lost. Only profiles with no such history are removed.')
                        ->action(function (Collection $records, StudentProfileDeleteService $deletes): void {
                            $deleted = 0;
                            $skipped = 0;
                            $actor = Auth::user();

                            foreach ($records as $student) {
                                if (! $student instanceof Student) {
                                    continue;
                                }

                                if ($deletes->hasProtectedHistory($student)) {
                                    $skipped++;

                                    continue;
                                }

                                try {
                                    $deletes->delete($student, $actor);
                                    $deleted++;
                                } catch (ValidationException) {
                                    $skipped++;
                                }
                            }

                            $notification = Notification::make()
                                ->title('Bulk delete')
                                ->body($deleted.' deleted. '.$skipped.' skipped to protect fee or attendance history.');

                            if ($deleted === 0) {
                                $notification->warning();
                            } else {
                                $notification->success();
                            }

                            $notification->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->recordActions([
                Action::make('openProfile')
                    ->label('Open profile')
                    ->icon(Heroicon::OutlinedUser)
                    ->url(fn (Student $record): string => StudentProfilePage::getUrl(['record' => $record->id])),
                Action::make('syncFaceVerify')
                    ->label('Sync to Face API')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (): bool => (bool) config('face_verify.enabled', false)
                        && (Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false))
                    ->requiresConfirmation()
                    ->modalHeading('Sync student to Face API')
                    ->modalDescription('Upserts this student into Face Verify so the kiosk can enroll by roll number.')
                    ->action(function (Student $record): void {
                        try {
                            $response = app(FaceVerifyGateService::class)->syncStudent($record);

                            Notification::make()
                                ->title('Synced to Face API')
                                ->body('Roll '.$record->activeEnrollment?->enrollment_number.' is ready for kiosk enrollment.')
                                ->success()
                                ->send();

                            unset($response);
                        } catch (Throwable $exception) {
                            Notification::make()
                                ->title('Face API sync failed')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->recordUrl(fn (Student $record): string => StudentProfilePage::getUrl(['record' => $record->id]))
            ->emptyStateHeading('No students found')
            ->emptyStateDescription('Enrolled students appear here. Use Search Student to look up a mobile or roll number.')
            ->emptyStateActions([
                Action::make('searchStudent')
                    ->label('Search Student')
                    ->icon(Heroicon::OutlinedMagnifyingGlass)
                    ->url(StudentSearchPage::getUrl()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudents::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
