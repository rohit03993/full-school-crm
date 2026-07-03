<?php

namespace App\Filament\Resources\Students\Pages;

use App\Enums\CrmPermission;
use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Forms\AddStudentFormSchema;
use App\Filament\Pages\StudentProfilePage;
use App\Filament\Pages\StudentSearchPage;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Batch;
use App\Models\Student;
use App\Services\StudentBulkImportService;
use App\Support\CrmAccess;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Auth;

class ListStudents extends ListRecords
{
    use ShowsCrmPageHint;

    protected static string $resource = StudentResource::class;

    protected static function crmHintKey(): ?string
    {
        return 'students.list';
    }

    public function mount(): void
    {
        parent::mount();

        if (request()->query('action') === 'addStudent' && $this->canAddStudent()) {
            $this->mountAction('addStudent');
        }
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.resources.students.missing-mobile-alert')
                    ->viewData(fn (): array => [
                        'count' => $this->missingMobileCount(),
                        'filterUrl' => StudentResource::getUrl('index', [
                            'filters' => [
                                'missing_mobile' => [
                                    'value' => true,
                                ],
                            ],
                        ]),
                    ]),
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        if ($this->canAddStudent()) {
            $actions[] = Action::make('addStudent')
                ->label('Add Student')
                ->icon(Heroicon::OutlinedUserPlus)
                ->color('primary')
                ->modalHeading('Add student')
                ->modalDescription('Enroll one student with roll number and batch. Fee is set from the course — adjust on the profile if needed.')
                ->modalSubmitActionLabel('Enroll student')
                ->fillForm(fn (): array => AddStudentFormSchema::initialState())
                ->form(fn (): array => AddStudentFormSchema::fields())
                ->action(function (array $data, StudentBulkImportService $imports): void {
                    $batch = Batch::query()->findOrFail((int) $data['batch_id']);

                    if ((int) $batch->academic_session_id !== (int) ($data['academic_session_id'] ?? 0)) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'batch_id' => 'Selected batch does not belong to this academic session.',
                        ]);
                    }

                    $student = $imports->enrollOne(Auth::user(), $batch, [
                        'roll_number' => $data['roll_number'],
                        'name' => $data['name'],
                        'father_name' => $data['father_name'] ?? null,
                        'mobile' => $data['mobile'] ?? null,
                        'date_of_birth' => $data['date_of_birth'] ?? null,
                        'gender' => $data['gender'] ?? null,
                    ]);

                    Notification::make()
                        ->title('Student enrolled')
                        ->body("{$student->name} is on the roster".(
                            $student->activeEnrollment?->enrollment_number
                                ? ' (roll '.$student->activeEnrollment->enrollment_number.')'
                                : ''
                        ).'.')
                        ->success()
                        ->send();

                    $this->redirect(
                        StudentProfilePage::getUrl(['record' => $student->id]),
                        navigate: true,
                    );
                });
        }

        $actions[] = Action::make('searchStudent')
            ->label('Search Student')
            ->icon(Heroicon::OutlinedMagnifyingGlass)
            ->url(StudentSearchPage::getUrl())
            ->color('gray');

        if ($this->missingMobileCount() > 0) {
            $actions[] = Action::make('missingMobile')
                ->label('Missing mobile ('.$this->missingMobileCount().')')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color('danger')
                ->url(StudentResource::getUrl('index', [
                    'filters' => [
                        'missing_mobile' => [
                            'value' => true,
                        ],
                    ],
                ]));
        }

        return $actions;
    }

    protected function canAddStudent(): bool
    {
        return CrmAccess::can(Auth::user(), CrmPermission::StudentsEdit);
    }

    protected function missingMobileCount(): int
    {
        return Student::query()->whereNull('mobile')->count();
    }
}
