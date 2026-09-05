<?php

namespace App\Filament\Pages;

use App\Enums\BatchStatus;
use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource;
use App\Models\Batch;
use App\Services\ParentFeeNoticeService;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class ParentFeeNoticesPage extends Page
{
    use RequiresCrmPermission;

    protected static bool $shouldRegisterNavigation = false;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::WhatsappCampaigns;
    }

    protected static function requiredLicenseFeature(): ?LicenseFeature
    {
        return LicenseFeature::WhatsApp;
    }

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Parent fee notices';

    protected static ?string $title = 'Parent fee notices';

    protected static ?string $slug = 'parent-fee-notices';

    protected static ?int $navigationSort = 26;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_META_WHATSAPP;

    public ?int $batchId = null;

    public ?int $templateId = null;

    public ?string $bulkAmount = null;

    public ?string $bulkDueDate = null;

    /**
     * @var list<array<string, mixed>>
     */
    public array $rows = [];

    public function getSubheading(): ?string
    {
        return 'Type pending amount and due date per student, then send one WhatsApp campaign. Amounts are for this message only — they are not saved as fees in CRM.';
    }

    public function mount(ParentFeeNoticeService $notices): void
    {
        $options = $notices->templateOptions();
        $this->templateId = $options !== [] ? (int) array_key_first($options) : null;
    }

    public function updatedBatchId(): void
    {
        $this->loadRoster();
    }

    public function loadRoster(): void
    {
        if (! $this->batchId) {
            $this->rows = [];

            return;
        }

        $batch = Batch::query()->find($this->batchId);

        if (! $batch) {
            $this->rows = [];

            return;
        }

        $this->rows = app(ParentFeeNoticeService::class)->rosterForBatch($batch);
    }

    public function applyBulkValues(): void
    {
        $amount = trim((string) ($this->bulkAmount ?? ''));
        $due = $this->normalizedBulkDueDate();

        if ($amount === '' && $due === '') {
            Notification::make()
                ->title('Nothing to apply')
                ->body('Enter an amount and/or due date first.')
                ->warning()
                ->send();

            return;
        }

        foreach ($this->rows as $index => $row) {
            if (! ($row['include'] ?? false)) {
                continue;
            }

            if ($amount !== '') {
                $this->rows[$index]['amount'] = $amount;
            }

            if ($due !== '') {
                $this->rows[$index]['due_date'] = $due;
            }
        }

        Notification::make()
            ->title('Applied to selected students')
            ->success()
            ->send();
    }

    protected function normalizedBulkDueDate(): string
    {
        if (blank($this->bulkDueDate)) {
            return '';
        }

        try {
            // HTML date inputs need Y-m-d; Filament pickers may store Carbon / display strings.
            return Carbon::parse($this->bulkDueDate)->toDateString();
        } catch (\Throwable) {
            return '';
        }
    }

    public function selectAllWithMobile(): void
    {
        foreach ($this->rows as $index => $row) {
            $this->rows[$index]['include'] = (bool) ($row['has_mobile'] ?? false);
        }
    }

    public function clearSelection(): void
    {
        foreach ($this->rows as $index => $row) {
            $this->rows[$index]['include'] = false;
        }
    }

    public function sendNotices(ParentFeeNoticeService $notices): void
    {
        if (! FeatureGate::enabled(LicenseFeature::WhatsApp)) {
            Notification::make()
                ->title('WhatsApp is off')
                ->danger()
                ->send();

            return;
        }

        $batch = Batch::query()->find($this->batchId);

        if (! $batch) {
            Notification::make()
                ->title('Select a batch')
                ->warning()
                ->send();

            return;
        }

        $template = $this->templateId
            ? \App\Models\WhatsAppTemplate::query()
                ->whereKey((int) $this->templateId)
                ->where('is_active', true)
                ->first()
            : null;

        if (! $template) {
            Notification::make()
                ->title('Select a template')
                ->warning()
                ->send();

            return;
        }

        try {
            $result = $notices->send($batch, $template, $this->rows, Auth::user());
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Check the form and try again.';

            Notification::make()
                ->title('Could not send')
                ->body((string) $message)
                ->danger()
                ->send();

            return;
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Could not send')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Parent fee notices queued')
            ->body($result['queued'].' message(s) queued. Delivery status is on the campaign page.')
            ->success()
            ->send();

        if (WhatsAppCampaignResource::canAccess()) {
            $this->redirect(WhatsAppCampaignResource::getUrl('view', ['record' => $result['campaign_id']]));
        }
    }

    /**
     * @return array<int, string>
     */
    protected function batchOptions(): array
    {
        return Batch::query()
            ->where('status', BatchStatus::Active)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Who to message')
                ->description('Pick a class, then enter pending amount and due date for each parent. Leave a row unchecked to skip.')
                ->schema([
                    Select::make('batchId')
                        ->label('Class / batch')
                        ->options(fn (): array => $this->batchOptions())
                        ->searchable()
                        ->required()
                        ->live()
                        ->native(false),
                    Select::make('templateId')
                        ->label('WhatsApp template')
                        ->options(fn (ParentFeeNoticeService $notices): array => $notices->templateOptions())
                        ->searchable()
                        ->required()
                        ->live()
                        ->helperText('Use an approved fee_reminder template (4 params). Avoid test templates with 0 params.')
                        ->native(false),
                ])
                ->columns(2)
                ->compact(),
            Section::make('Quick fill')
                ->description('Optional — apply the same amount and/or due date to every checked student.')
                ->schema([
                    TextInput::make('bulkAmount')
                        ->label('Amount for all selected')
                        ->numeric()
                        ->minValue(0.01)
                        ->placeholder('e.g. 5000'),
                    TextInput::make('bulkDueDate')
                        ->label('Due date for all selected')
                        ->type('date')
                        ->helperText('Pick a date, then click Apply to selected.'),
                    Actions::make([
                        Action::make('applyBulkValues')
                            ->label('Apply to selected')
                            ->color('gray')
                            ->action('applyBulkValues'),
                        Action::make('selectAllWithMobile')
                            ->label('Select all with mobile')
                            ->color('gray')
                            ->action('selectAllWithMobile'),
                        Action::make('clearSelection')
                            ->label('Clear selection')
                            ->color('gray')
                            ->action('clearSelection'),
                    ])->alignment(Alignment::Start)->columnSpanFull(),
                ])
                ->columns(2)
                ->compact()
                ->visible(fn (): bool => $this->rows !== []),
            Section::make('Message preview')
                ->description('Shows how the WhatsApp will look for the first selected student with amount and due date filled.')
                ->schema([
                    View::make('filament.pages.partials.parent-fee-notices-preview')
                        ->viewData(fn (): array => [
                            'preview' => app(ParentFeeNoticeService::class)->preview($this->rows, $this->templateId),
                        ]),
                ])
                ->compact()
                ->visible(fn (): bool => $this->rows !== []),
            Section::make('Students')
                ->schema([
                    View::make('filament.pages.partials.parent-fee-notices-grid')
                        ->viewData(fn (): array => [
                            'rows' => $this->rows,
                        ]),
                    Actions::make([
                        Action::make('sendNotices')
                            ->label('Send WhatsApp notices')
                            ->icon(Heroicon::OutlinedPaperAirplane)
                            ->color('primary')
                            ->requiresConfirmation()
                            ->modalHeading('Send parent fee notices?')
                            ->modalDescription('Each selected parent will get a WhatsApp with the amount and due date you entered. This does not change fee balances in CRM.')
                            ->action('sendNotices'),
                    ])->alignment(Alignment::Start),
                ])
                ->compact()
                ->visible(fn (): bool => $this->rows !== []),
        ]);
    }
}
