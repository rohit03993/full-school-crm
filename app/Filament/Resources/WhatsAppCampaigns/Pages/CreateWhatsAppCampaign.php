<?php

namespace App\Filament\Resources\WhatsAppCampaigns\Pages;

use App\Enums\WhatsAppAudienceType;
use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource;
use App\Services\WhatsAppCampaignService;
use App\Support\WhatsAppCampaignFormHelper;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class CreateWhatsAppCampaign extends CreateRecord
{
    use ShowsCrmPageHint;

    protected static string $resource = WhatsAppCampaignResource::class;

    protected static ?string $title = 'New campaign';

    protected static function crmHintKey(): ?string
    {
        return 'whatsapp.campaigns.create';
    }

    public function mount(): void
    {
        parent::mount();

        $this->form->fill(array_merge($this->form->getState(), [
            'name' => WhatsAppCampaignFormHelper::generateDefaultName(),
            'send_immediately' => true,
            'audience_type' => WhatsAppAudienceType::Batch->value,
        ]));
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['send_immediately']);

        if (($data['audience_type'] ?? null) !== WhatsAppAudienceType::Batch->value) {
            $data['batch_id'] = null;
        }

        $variables = $data['campaign_variables'] ?? [];
        $manual = collect($data['template_manual_params'] ?? [])
            ->filter(fn ($value): bool => filled($value))
            ->all();

        if ($manual !== []) {
            $variables['_manual'] = $manual;
        }

        if (filled($variables['date'] ?? null)) {
            $variables['date'] = Carbon::parse($variables['date'])->format('d M Y');
        }

        if (filled($variables['time'] ?? null)) {
            $variables['time'] = Carbon::parse($variables['time'])->format('g:i A');
        }

        $data['campaign_variables'] = $variables === [] ? null : $variables;
        unset($data['template_manual_params']);

        if (blank($data['name'] ?? null)) {
            $data['name'] = WhatsAppCampaignFormHelper::generateDefaultName();
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(WhatsAppCampaignService::class)->createCampaign($data, Auth::user());
    }

    protected function afterCreate(): void
    {
        if ((bool) data_get($this->form->getRawState(), 'send_immediately', true)) {
            app(WhatsAppCampaignService::class)->queueCampaign($this->record, Auth::user());
        }
    }

    protected function getRedirectUrl(): string
    {
        return WhatsAppCampaignResource::getUrl('view', ['record' => $this->record]);
    }
}
