<?php

namespace App\Filament\Resources\WhatsAppCampaigns\Pages;

use App\Enums\WhatsAppCampaignStatus;
use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource;
use App\Services\WhatsAppCampaignService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

class ViewWhatsAppCampaign extends ViewRecord
{
    protected static string $resource = WhatsAppCampaignResource::class;

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
                ->visible(fn (): bool => ! in_array($this->record->status, [
                    WhatsAppCampaignStatus::Completed,
                ], true))
                ->action(function (): void {
                    app(WhatsAppCampaignService::class)->queueCampaign($this->record, Auth::user());

                    Notification::make()
                        ->title('Campaign queued')
                        ->body('Messages are being sent in batches. Check progress on this page.')
                        ->success()
                        ->send();

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
