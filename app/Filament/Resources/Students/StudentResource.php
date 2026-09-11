<?php

namespace App\Filament\Resources\Students;

use App\Enums\CrmPermission;
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
use App\Services\StudentProfileDeleteService;
use App\Support\BatchSelectOptions;
use App\Support\ClassSectionLabel;
use App\Support\CrmAccess;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
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
            ->filtersLayout(FiltersLayout::Modal)
            ->filtersFormColumns(1)
            ->filtersTriggerAction(fn (Action $action): Action => $action
                ->button()
                ->label('Filters')
                ->icon(Heroicon::OutlinedFunnel)
                ->color('gray'))
            ->searchPlaceholder('Name, roll no., or mobile…')
            ->searchDebounce('400ms')
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->columnManager(false)
            ->extraAttributes(['class' => 'crm-students-directory'])
            ->recordClasses(fn (Student $record): ?string => blank($record->mobile)
                ? 'crm-students-directory__row--missing-mobile'
                : null)
            ->columns([
                TextColumn::make('name')
                    ->label('Student')
                    ->searchable(query: function (Builder $query, string $search): void {
                        $term = '%'.self::escapeLike(trim($search)).'%';
                        $digits = preg_replace('/\D+/', '', $search) ?? '';

                        $query->where(function (Builder $inner) use ($term, $digits): void {
                            $inner->where('name', 'like', $term)
                                ->orWhere('father_name', 'like', $term)
                                ->orWhere('mobile', 'like', $term)
                                ->orWhereHas(
                                    'activeEnrollment',
                                    fn (Builder $enrollment): Builder => $enrollment->where('enrollment_number', 'like', $term),
                                );

                            if (strlen($digits) >= 4) {
                                $inner->orWhere('mobile', 'like', '%'.$digits.'%');
                            }
                        });
                    })
                    ->sortable()
                    ->weight('semibold')
                    ->description(function (Student $record): string {
                        $roll = $record->activeEnrollment?->enrollment_number;
                        $batch = $record->activeBatchStudent?->batch;
                        $class = $batch
                            ? ClassSectionLabel::forBatch($batch, includeSession: false, includeShift: false)
                            : ($record->activeEnrollment?->course?->name
                                ? $record->activeEnrollment->course->name.' · No section'
                                : null);

                        return collect([
                            filled($roll) ? 'Roll '.$roll : null,
                            $class,
                        ])->filter()->implode(' · ');
                    })
                    ->wrap()
                    ->url(fn (Student $record): string => StudentProfilePage::getUrl(['record' => $record->id]))
                    ->color('primary'),
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
                BulkAction::make('assignSection')
                    ->label('Assign section')
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->button()
                    ->color('primary')
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
                BulkActionGroup::make([
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
                    BulkAction::make('syncFaceVerifyBulk')
                        ->label('Sync selected to Face API')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->visible(fn (): bool => (bool) config('face_verify.enabled', false)
                            && (Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false))
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $synced = 0;
                            $failed = 0;
                            $faceVerify = app(FaceVerifyGateService::class);

                            foreach ($records as $student) {
                                if (! $student instanceof Student) {
                                    continue;
                                }

                                try {
                                    $faceVerify->syncStudent($student);
                                    $synced++;
                                } catch (Throwable) {
                                    $failed++;
                                }
                            }

                            Notification::make()
                                ->title('Face API sync')
                                ->body($synced.' synced'.($failed > 0 ? ", {$failed} failed" : '').'.')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ])
                    ->label('More')
                    ->button()
                    ->labeledFrom('xs'),
            ])
            ->recordUrl(fn (Student $record): string => StudentProfilePage::getUrl(['record' => $record->id]))
            ->emptyStateHeading('No students found')
            ->emptyStateDescription('Try another name, roll number, or mobile — or clear filters.');
    }

    protected static function escapeLike(string $value): string
    {
        return str_replace(['%', '_'], ['\\%', '\\_'], $value);
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
