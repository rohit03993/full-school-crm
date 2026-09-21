<?php

namespace App\Filament\Resources\WhatsAppCampaigns\Pages;

use App\Enums\WhatsAppCampaignStatus;
use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource;
use App\Models\WhatsAppCampaign;
use App\Services\WhatsAppCampaignService;
use App\Support\CrmAccess;
use App\Support\WhatsAppSendUi;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

class ViewWhatsAppCampaign extends ViewRecord
{
    protected static string $resource = WhatsAppCampaignResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? request()->route('record');

        if (! $record instanceof WhatsAppCampaign && filled($record)) {
            $record = WhatsAppCampaign::query()->find($record);
        }

        if ($record instanceof WhatsAppCampaign) {
            return WhatsAppCampaignResource::canView($record);
        }

        // Filament may check access before the campaign id is bound.
        // Staff who can click Send on the mark sheet must reach this progress page.
        return WhatsAppCampaignResource::canAccess()
            || CrmAccess::canSendExamMarksWhatsApp(Auth::user());
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record->load(['template', 'course', 'batch']);
    }

    #[Computed]
    public function campaignInProgress(): bool
    {
        return in_array($this->record->status, [
            WhatsAppCampaignStatus::Queued,
            WhatsAppCampaignStatus::Running,
        ], true);
    }

    public function refreshCampaignProgress(): void
    {
        if (! $this->campaignInProgress) {
            return;
        }

        $this->record = $this->record->fresh(['template']);
    }

    public function getSubheading(): ?string
    {
        if (! $this->campaignInProgress) {
            return null;
        }

        $total = (int) $this->record->total_recipients;
        $done = (int) $this->record->sent_count + (int) $this->record->failed_count;

        return "Sending in progress — {$done} of {$total} processed. This page updates automatically.";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshProgress')
                ->label('Refresh progress')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn (): bool => $this->campaignInProgress)
                ->action(function (): void {
                    $this->refreshCampaignProgress();
                    $this->dispatch('$refresh');
                }),
            Action::make('sendNow')
                ->label('Send / resume')
                ->icon('heroicon-o-paper-airplane')
                ->extraAttributes(WhatsAppSendUi::loadingAttributes())
                ->visible(fn (): bool => in_array($this->record->status, [
                    WhatsAppCampaignStatus::Draft,
                    WhatsAppCampaignStatus::Paused,
                ], true))
                ->action(function (): void {
                    try {
                        app(WhatsAppCampaignService::class)->queueCampaign($this->record, Auth::user(), wait: false);

                        Notification::make()
                            ->title('Sending started')
                            ->body('Watch the counter on this page. Do not click Send again.')
                            ->success()
                            ->send();
                    } catch (\RuntimeException $exception) {
                        Notification::make()
                            ->title('Cannot send campaign')
                            ->body($exception->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }

                    $this->record->refresh();
                }),
            Action::make('pause')
                ->label('Pause')
                ->color('warning')
                ->visible(fn (): bool => in_array($this->record->status, [
                    WhatsAppCampaignStatus::Queued,
                    WhatsAppCampaignStatus::Running,
                ], true))
                ->action(function (): void {
                    $this->record->update(['status' => WhatsAppCampaignStatus::Paused]);

                    Notification::make()->title('Campaign paused')->success()->send();
                }),
        ];
    }

    public function getFooter(): ?\Illuminate\Contracts\View\View
    {
        if (! $this->campaignInProgress) {
            return null;
        }

        return view('filament.pages.whatsapp-campaign-poll');
    }
}
