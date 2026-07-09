<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Enums\RoleName;
use App\Models\Batch;
use App\Models\ResultDeclaration;
use App\Services\ConsolidatedMarksheetPdfService;
use App\Support\CrmAccess;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use App\Support\ExamTestGroupMatrix;
use App\Support\FeatureGate;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ConsolidatedReportCardsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?string $navigationLabel = 'Consolidated report cards';

    protected static ?string $title = 'Consolidated report cards';

    protected static ?string $slug = 'consolidated-report-cards';

    protected static ?int $navigationSort = 27;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_ACADEMICS;

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Marksheets)) {
            return false;
        }

        return Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false;
    }

    public ?int $batchId = null;

    /** @var list<string> */
    public array $selectedGroupKeys = [];

    public ?string $issueDate = null;

    /** @var list<array{student_id: int, student_name: string, roll_number: string, pdf_path: string}> */
    public array $generatedReports = [];

    public function mount(): void
    {
        $this->issueDate = now()->toDateString();
        $this->batchId = request()->integer('batch') ?: null;
    }

    public function generateReports(ConsolidatedMarksheetPdfService $pdf): void
    {
        abort_unless(self::canAccess(), 403);

        $this->validate([
            'batchId' => 'required|exists:batches,id',
            'selectedGroupKeys' => 'required|array|min:2',
            'selectedGroupKeys.*' => 'required|string',
            'issueDate' => 'required|date',
        ]);

        $batch = Batch::query()->findOrFail($this->batchId);

        try {
            $this->generatedReports = $pdf->generateForBatch(
                $batch,
                $this->selectedGroupKeys,
                Auth::user(),
                $this->issueDate,
            );

            Notification::make()
                ->title('Consolidated report cards generated')
                ->body(count($this->generatedReports).' student report card(s) ready for download.')
                ->success()
                ->duration(10000)
                ->send();
        } catch (\Illuminate\Validation\ValidationException $exception) {
            Notification::make()
                ->title('Could not generate report cards')
                ->body(collect($exception->errors())->flatten()->first())
                ->danger()
                ->send();
        }
    }

    /**
     * @return array<int, string>
     */
    public function batchOptions(): array
    {
        return Batch::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return list<array{group_key: string, label: string, date: string}>
     */
    public function publishedExamOptions(): array
    {
        if (! $this->batchId) {
            return [];
        }

        $matrix = ExamTestGroupMatrix::build($this->batchId, null);
        $options = [];

        foreach ($matrix['rows'] ?? [] as $row) {
            $groupKey = (string) ($row['group_key'] ?? '');

            if ($groupKey === '') {
                continue;
            }

            $declaration = ResultDeclaration::query()->where('group_key', $groupKey)->first();

            if (! $declaration?->isPublished()) {
                continue;
            }

            $options[] = [
                'group_key' => $groupKey,
                'label' => (string) ($row['test_label'] ?? $groupKey),
                'date' => $row['date']?->format('d M Y') ?? '—',
            ];
        }

        return $options;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.consolidated-report-cards')
                ->viewData(fn (): array => [
                    'batchOptions' => $this->batchOptions(),
                    'publishedExamOptions' => $this->publishedExamOptions(),
                    'generatedReports' => $this->generatedReports,
                    'examResultsUrl' => \App\Filament\Resources\ActivitySessions\ActivitySessionResource::getUrl('index'),
                ]),
        ]);
    }

    public function getSubheading(): ?string
    {
        return 'Combine multiple published exams (e.g. Term 1 + Term 2) into one report card PDF per student.';
    }
}
