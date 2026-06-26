<?php

namespace App\Filament\Resources\Students;

use App\Enums\CrmPermission;
use App\Enums\StudentStatus;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Filament\Pages\StudentProfilePage;
use App\Filament\Pages\StudentSearchPage;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Support\CrmTable;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Student;
use App\Support\CrmNavigation;
use App\Support\InstituteProfile;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
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

    protected static ?string $navigationLabel = 'All Students';

    protected static ?string $modelLabel = 'Student';

    protected static ?string $pluralModelLabel = 'Students';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 10;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_STUDENTS;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'activeEnrollment.course',
                'activeEnrollment.academicSession',
                'activeBatchStudent.batch',
            ]);
    }

    public static function table(Table $table): Table
    {
        return CrmTable::configure($table)
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
                    ->label('Course')
                    ->placeholder('—')
                    ->limit(30)
                    ->toggleable(),
                TextColumn::make('activeEnrollment.academicSession.name')
                    ->label('Session')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('activeBatchStudent.batch.name')
                    ->label('Batch')
                    ->placeholder('—')
                    ->toggleable(),
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
                    ->options(collect(StudentStatus::cases())->mapWithKeys(
                        fn (StudentStatus $status) => [$status->value => $status->label()],
                    )),
                SelectFilter::make('course')
                    ->label('Course')
                    ->options(fn (): array => InstituteProfile::activeCourseOptions())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query): Builder => $query->whereHas(
                            'activeEnrollment',
                            fn (Builder $query): Builder => $query->where('course_id', $data['value']),
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
            ])
            ->recordActions([
                Action::make('openProfile')
                    ->label('Open profile')
                    ->icon(Heroicon::OutlinedUser)
                    ->url(fn (Student $record): string => StudentProfilePage::getUrl(['record' => $record->id])),
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
