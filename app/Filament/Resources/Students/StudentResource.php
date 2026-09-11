<?php

namespace App\Filament\Resources\Students;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Filament\Pages\StudentProfilePage;
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
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns([
                'default' => 2,
                'md' => 3,
                'xl' => 5,
            ])
            ->searchPlaceholder('Name, roll no., or mobile…')
            ->searchDebounce('400ms')
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->extraAttributes(['class' => 'crm-students-directory'])
            ->recordClasses(fn (Student $record): ?string => blank($record->mobile)
                ? 'bg-danger-50/60 dark:bg-danger-500/5'
                : null)
            ->columns([
                TextColumn::make('name')
                    ->label('Student')
                    ->searchable(query: function (Builder $query, string $search): void {
                        $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($search)).'%';

                        $query->where(function (Builder $inner) use ($term): void {
                            $inner->where('name', 'like', $term)
                                ->orWhere('father_name', 'like', $term);
                        });
                    })
                    ->sortable()
                    ->weight('bold')
                    ->url(fn (Student $record): string => StudentProfilePage::getUrl(['record' => $record->id]))
                    ->color('primary'),
                TextColumn::make('activeEnrollment.enrollment_number')
                    ->label('Roll No.')
                    ->searchable(query: function (Builder $query, string $search): void {
                        $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($search)).'%';

                        $query->whereHas(
                            'activeEnrollment',
                            fn (Builder $enrollment): Builder => $enrollment->where('enrollment_number', 'like', $term),
                        );
                    })
                    ->sortable()
                    ->fontFamily('mono')
                    ->weight('semibold')
                    ->placeholder('—'),
                TextColumn::make('class_section')
                    ->label('Class')
                    ->state(function (Student $record): string {
                        $batch = $record->activeBatchStudent?->batch;

                        if ($batch) {
                            return ClassSectionLabel::forBatch($batch, includeSession: false, includeShift: false);
                        }

                        $courseName = $record->activeEnrollment?->course?->name;

                        return filled($courseName)
                            ? $courseName.' · No section'
                            : '—';
                    })
                    ->wrap()
                    ->toggleable(false),
                TextColumn::make('mobile')
                    ->label('Mobile')
                    ->searchable(query: function (Builder $query, string $search): void {
                        $digits = preg_replace('/\D+/', '', $search) ?? '';
                        $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($search)).'%';

                        $query->where(function (Builder $inner) use ($term, $digits): void {
                            $inner->where('mobile', 'like', $term);
                            if (strlen($digits) >= 4) {
                                $inner->orWhere('mobile', 'like', '%'.$digits.'%');
                            }
                        });
                    })
                    ->fontFamily('mono')
                    ->copyable(fn (?string $state): bool => filled($state))
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? $state : 'Missing — add from profile')
                    ->badge(fn (?string $state): bool => blank($state))
                    ->color(fn (?string $state): string => blank($state) ? 'danger' : 'gray')
                    ->visibleFrom('md'),
                TextColumn::make('activeEnrollment.feeStructure.pending_amount')
                    ->label('Fee pending')
                    ->money('INR')
                    ->placeholder('—')
                    ->sortable()
                    ->color(fn ($state): string => (float) ($state ?? 0) > 0 ? 'warning' : 'success')
                    ->visibleFrom('md')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->hidden(fn (): bool => ! FeatureGate::enabled(LicenseFeature::Fees) || ! CrmAccess::canViewFees(Auth::user())),
                TextColumn::make('fee_next_due')
                    ->label('Next due')
                    ->state(function (Student $record): ?string {
                        $date = app(FeesDashboardService::class)->nextDueDateForStudent($record);

                        return $date?->format('d M Y');
                    })
                    ->placeholder('—')
                    ->visibleFrom('md')
                    ->toggleable(isToggledHiddenByDefault: true)
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
                    ->visibleFrom('md')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->hidden(fn (): bool => ! FeatureGate::enabled(LicenseFeature::Fees) || ! CrmAccess::canViewFees(Auth::user())),
                TextColumn::make('activeEnrollment.academicSession.name')
                    ->label('Session')
                    ->placeholder('—')
                    ->visibleFrom('md')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (StudentStatus $state): string => match ($state) {
                        StudentStatus::Enrolled => 'success',
                        StudentStatus::Enquiry => 'gray',
                        StudentStatus::Completed => 'info',
                        StudentStatus::Dropped => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (StudentStatus $state): string => $state->label())
                    ->visibleFrom('md')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('activeEnrollment.enrolled_at')
                    ->label('Enrolled')
                    ->date('d M Y')
                    ->sortable()
                    ->visibleFrom('md')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
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
                SelectFilter::make('status')
                    ->options(collect([
                        StudentStatus::Enrolled,
                        StudentStatus::Completed,
                        StudentStatus::Dropped,
                    ])->mapWithKeys(
                        fn (StudentStatus $status) => [$status->value => $status->label()],
                    )),
                TernaryFilter::make('section_assigned')
                    ->label('Section')
                    ->placeholder('All')
                    ->trueLabel('Has section')
                    ->falseLabel('No section')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('activeBatchStudent'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('activeBatchStudent'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('missing_mobile')
                    ->label('Mobile')
                    ->placeholder('All')
                    ->trueLabel('Missing mobile')
                    ->falseLabel('Has mobile')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNull('mobile'),
                        false: fn (Builder $query): Builder => $query->whereNotNull('mobile'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
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
                Action::make('syncFaceVerify')
                    ->label('Sync Face')
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
            ->emptyStateDescription('Try another name, roll number, or mobile — or clear filters.');
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
