<?php

namespace App\Filament\Pages;

use App\Enums\CertificateType;
use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Models\Student;
use App\Models\StudentCertificate;
use App\Services\CertificateService;
use App\Support\CrmAccess;
use App\Support\CrmHint;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use App\Support\CrmPagination;
use App\Support\FeatureGate;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\WithPagination;
use UnitEnum;

class CertificatesPage extends Page
{
    use WithPagination;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?string $navigationLabel = null;

    protected static ?string $title = null;

    protected static ?string $slug = 'certificates';

    protected static ?int $navigationSort = 33;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_STUDENTS;

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::certificates();
    }

    public function getTitle(): string
    {
        return CrmMenuLabels::certificates();
    }

    public function getSubheading(): ?string
    {
        return CrmHint::text('certificates.page');
    }

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Certificates)) {
            return false;
        }

        return CrmAccess::can(Auth::user(), CrmPermission::CertificatesView);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public string $search = '';

    public string $typeFilter = '';

    public string $issueStudentSearch = '';

    public ?int $issueStudentId = null;

    public string $issueType = '';

    public string $issueDate = '';

    public string $issueRemarks = '';

    public function mount(): void
    {
        $this->issueDate = today()->toDateString();
        $this->issueType = CertificateType::Bonafide->value;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedIssueStudentSearch(): void
    {
        $this->issueStudentId = null;
    }

    public function selectIssueStudent(int $studentId): void
    {
        $this->issueStudentId = $studentId;
        $student = Student::query()->find($studentId);
        $this->issueStudentSearch = $student
            ? trim($student->name.' · '.($student->mobile ?? ''))
            : '';
    }

    public function clearIssueStudent(): void
    {
        $this->issueStudentId = null;
        $this->issueStudentSearch = '';
    }

    public function issueCertificate(CertificateService $certificates): void
    {
        if (! CrmAccess::can(Auth::user(), CrmPermission::CertificatesIssue)) {
            Notification::make()->title('You cannot issue certificates.')->danger()->send();

            return;
        }

        $this->validate([
            'issueStudentId' => 'required|integer|exists:students,id',
            'issueType' => 'required|in:'.implode(',', array_column(CertificateType::cases(), 'value')),
            'issueDate' => 'required|date',
            'issueRemarks' => 'nullable|string|max:1000',
        ], [
            'issueStudentId.required' => 'Select an enrolled student.',
        ]);

        $student = Student::query()->findOrFail($this->issueStudentId);

        try {
            $certificate = $certificates->issue(
                $student,
                CertificateType::from($this->issueType),
                Auth::user(),
                [
                    'issued_on' => $this->issueDate,
                    'remarks' => $this->issueRemarks,
                ],
            );
        } catch (ValidationException $e) {
            Notification::make()
                ->title(collect($e->errors())->flatten()->first() ?: 'Could not issue certificate.')
                ->danger()
                ->send();

            return;
        }

        $this->issueRemarks = '';
        $this->clearIssueStudent();
        $this->resetPage();

        Notification::make()
            ->title('Certificate issued')
            ->body($certificate->type->label().' · '.$certificate->serial_number)
            ->success()
            ->send();
    }

    /**
     * @return list<Student>
     */
    public function issueStudentSuggestions(): array
    {
        $term = trim($this->issueStudentSearch);

        if ($this->issueStudentId !== null || strlen($term) < 2) {
            return [];
        }

        $digits = preg_replace('/\D/', '', $term);

        return Student::query()
            ->whereHas('activeEnrollment')
            ->where(function (Builder $query) use ($term, $digits): void {
                $query->where('name', 'like', '%'.$term.'%');

                if (filled($digits)) {
                    $query->orWhere('mobile', 'like', '%'.$digits.'%');
                }
            })
            ->with(['activeEnrollment'])
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->all();
    }

    protected function certificatesPaginator(): LengthAwarePaginator
    {
        $query = StudentCertificate::query()
            ->with(['student.activeEnrollment', 'issuedBy', 'enrollment'])
            ->orderByDesc('issued_on')
            ->orderByDesc('id');

        if ($this->typeFilter !== '') {
            $query->where('type', $this->typeFilter);
        }

        if (trim($this->search) !== '') {
            $term = trim($this->search);
            $digits = preg_replace('/\D/', '', $term);

            $query->where(function (Builder $inner) use ($term, $digits): void {
                $inner->where('serial_number', 'like', '%'.$term.'%')
                    ->orWhereHas('student', function (Builder $studentQuery) use ($term, $digits): void {
                        $studentQuery->where('name', 'like', '%'.$term.'%');

                        if (filled($digits)) {
                            $studentQuery->orWhere('mobile', 'like', '%'.$digits.'%');
                        }
                    });
            });
        }

        return $query->paginate(CrmPagination::PER_PAGE);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.certificates')
                ->viewData(fn (): array => [
                    'certificates' => $this->certificatesPaginator(),
                    'typeOptions' => CertificateType::options(),
                    'canIssue' => CrmAccess::can(Auth::user(), CrmPermission::CertificatesIssue),
                    'issueSuggestions' => $this->issueStudentSuggestions(),
                ]),
        ]);
    }
}
