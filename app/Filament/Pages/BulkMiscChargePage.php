<?php

namespace App\Filament\Pages;

use App\Enums\BatchStatus;
use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Filament\Forms\AddMiscChargeFormSchema;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Course;
use App\Services\FeeMiscChargeService;
use App\Support\CrmAccess;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use App\Support\InstituteProfile;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class BulkMiscChargePage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Bulk misc charges';

    protected static ?string $title = 'Add misc charge in bulk';

    protected static ?int $navigationSort = 26;

    protected static string | UnitEnum | null $navigationGroup = CrmNavigation::GROUP_STUDENTS;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * @var Collection<int, array<string, mixed>>
     */
    public Collection $recentChargeSummaries;

    /**
     * @var Collection<int, array<string, mixed>>
     */
    public Collection $scopeChargeSummaries;

    public function boot(): void
    {
        $this->recentChargeSummaries = collect();
        $this->scopeChargeSummaries = collect();
    }

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Fees)) {
            return false;
        }

        return CrmAccess::can(Auth::user(), CrmPermission::FeesAdjustStructure);
    }

    public function mount(FeeMiscChargeService $miscCharges): void
    {
        $this->form->fill([
            'scope' => 'batch',
            'course_id' => null,
            'batch_id' => null,
            'academic_session_id' => null,
            'label' => '',
            'amount' => null,
            'due_date' => null,
        ]);

        $this->refreshChargeSummaries($miscCharges);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Who receives this charge?')
                ->schema([
                    Select::make('scope')
                        ->label('Apply to')
                        ->options([
                            'batch' => 'One batch (class section)',
                            'course' => 'Whole course (all active enrollments)',
                        ])
                        ->required()
                        ->native(false)
                        ->live(),
                    Select::make('course_id')
                        ->label('Course')
                        ->options(InstituteProfile::activeCourseOptions())
                        ->searchable()
                        ->required()
                        ->native(false)
                        ->live(),
                    Select::make('batch_id')
                        ->label('Batch')
                        ->options(fn (callable $get): array => $this->batchOptions((int) ($get('course_id') ?? 0)))
                        ->searchable()
                        ->native(false)
                        ->visible(fn (callable $get): bool => $get('scope') === 'batch')
                        ->required(fn (callable $get): bool => $get('scope') === 'batch')
                        ->live(),
                    Select::make('academic_session_id')
                        ->label('Academic session (optional)')
                        ->options(fn (): array => AcademicSession::query()->orderByDesc('start_date')->pluck('name', 'id')->all())
                        ->searchable()
                        ->native(false)
                        ->visible(fn (callable $get): bool => $get('scope') === 'course')
                        ->placeholder('All sessions')
                        ->live(),
                ])
                ->columns(2),
            Section::make('Charge details')
                ->schema(AddMiscChargeFormSchema::fields())
                ->columns(2),
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function batchOptions(int $courseId): array
    {
        if ($courseId <= 0) {
            return [];
        }

        return Batch::query()
            ->where('course_id', $courseId)
            ->where('status', BatchStatus::Active)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function updatedDataScope(): void
    {
        $this->loadScopeChargeSummaries();
    }

    public function updatedDataCourseId(): void
    {
        $this->loadScopeChargeSummaries();
    }

    public function updatedDataBatchId(): void
    {
        $this->loadScopeChargeSummaries();
    }

    public function updatedDataAcademicSessionId(): void
    {
        $this->loadScopeChargeSummaries();
    }

    public function refreshChargeSummaries(?FeeMiscChargeService $miscCharges = null): void
    {
        $miscCharges ??= app(FeeMiscChargeService::class);

        $this->recentChargeSummaries = $miscCharges->recentSeparateChargeSummaries();
        $this->loadScopeChargeSummaries($miscCharges);
    }

    protected function loadScopeChargeSummaries(?FeeMiscChargeService $miscCharges = null): void
    {
        $miscCharges ??= app(FeeMiscChargeService::class);
        $data = $this->data ?? [];
        $scope = (string) ($data['scope'] ?? 'batch');
        $courseId = (int) ($data['course_id'] ?? 0);

        if ($courseId <= 0) {
            $this->scopeChargeSummaries = collect();

            return;
        }

        if ($scope === 'batch') {
            $batchId = (int) ($data['batch_id'] ?? 0);

            if ($batchId <= 0) {
                $this->scopeChargeSummaries = collect();

                return;
            }

            $batch = Batch::query()->find($batchId);
            $this->scopeChargeSummaries = $batch
                ? $miscCharges->scopeSeparateChargeSummariesForBatch($batch)
                : collect();

            return;
        }

        $course = Course::query()->find($courseId);

        if (! $course) {
            $this->scopeChargeSummaries = collect();

            return;
        }

        $sessionId = filled($data['academic_session_id'] ?? null) ? (int) $data['academic_session_id'] : null;
        $this->scopeChargeSummaries = $miscCharges->scopeSeparateChargeSummariesForCourse($course, $sessionId);
    }

    public function submit(FeeMiscChargeService $miscCharges): void
    {
        $data = $this->form->getState();
        $label = (string) ($data['label'] ?? '');
        $amount = (float) ($data['amount'] ?? 0);
        $dueDate = filled($data['due_date'] ?? null) ? (string) $data['due_date'] : null;

        if (($data['scope'] ?? 'batch') === 'batch') {
            $batch = Batch::query()->findOrFail((int) $data['batch_id']);
            $created = $miscCharges->bulkAddForBatch($batch, $label, $amount, $dueDate, Auth::user());
        } else {
            $course = Course::query()->findOrFail((int) $data['course_id']);
            $sessionId = filled($data['academic_session_id'] ?? null) ? (int) $data['academic_session_id'] : null;
            $created = $miscCharges->bulkAddForCourse($course, $label, $amount, $dueDate, Auth::user(), $sessionId);
        }

        Notification::make()
            ->title('Misc charges added')
            ->body(count($created).' student(s) received “'.$label.'” (₹'.number_format($amount, 2).').')
            ->success()
            ->send();

        $this->form->fill([
            ...$this->data,
            'label' => '',
            'amount' => null,
            'due_date' => null,
        ]);

        $this->refreshChargeSummaries($miscCharges);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('bulkMiscForm')
                ->livewireSubmitHandler('submit')
                ->footer([
                    Actions::make([
                        \Filament\Actions\Action::make('submit')
                            ->label('Add charge to students')
                            ->submit('submit'),
                    ]),
                ]),
            View::make('filament.pages.partials.bulk-misc-charge-history')
                ->viewData(fn (): array => [
                    'recentChargeSummaries' => $this->recentChargeSummaries,
                    'scopeChargeSummaries' => $this->scopeChargeSummaries,
                ]),
        ]);
    }
}
