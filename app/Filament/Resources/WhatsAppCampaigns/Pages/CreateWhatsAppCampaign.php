<?php

namespace App\Filament\Resources\WhatsAppCampaigns\Pages;

use App\Enums\WhatsAppAudienceType;
use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource;
use App\Services\MetaWhatsAppMediaService;
use App\Services\WhatsAppCampaignService;
use App\Support\WhatsAppCampaignFormHelper;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

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

        $header = $this->pdfHeaderForCampaign($data['whatsapp_template_id'] ?? null);

        if ($header !== null) {
            $variables['_header'] = $header;
        }

        $data['campaign_variables'] = $variables === [] ? null : $variables;
        unset($data['template_manual_params'], $data['campaign_header_pdf']);

        if (blank($data['name'] ?? null)) {
            $data['name'] = WhatsAppCampaignFormHelper::generateDefaultName();
        }

        return $data;
    }

    /**
     * One PDF for every parent in this campaign. Text templates return null.
     *
     * @return array{type: string, id: string, path: ?string, mime: ?string, filename: ?string}|null
     */
    protected function pdfHeaderForCampaign(mixed $templateId): ?array
    {
        if (WhatsAppCampaignResource::selectedTemplateHeaderFormat($templateId) !== 'DOCUMENT') {
            return null;
        }

        $fileState = data_get($this->form->getRawState(), 'campaign_header_pdf');

        if (is_array($fileState)) {
            $fileState = $fileState[array_key_first($fileState)] ?? null;
        }

        $media = app(MetaWhatsAppMediaService::class);
        $prepared = $fileState instanceof UploadedFile
            ? $media->headerMediaForTemplateSend($fileState, 'DOCUMENT')
            : $media->headerMediaForStoredFile(is_string($fileState) ? $fileState : null, 'DOCUMENT');

        $header = is_array($prepared['header'] ?? null) ? $prepared['header'] : null;

        if (($prepared['status'] ?? '') !== 'success' || blank($header['id'] ?? null)) {
            throw ValidationException::withMessages([
                'campaign_header_pdf' => (string) ($prepared['error'] ?? 'Choose a PDF for this template before sending.'),
            ]);
        }

        return [
            'type' => strtolower((string) ($header['type'] ?? 'document')),
            'id' => (string) ($header['id'] ?? ''),
            'path' => $header['path'] ?? null,
            'mime' => $header['mime'] ?? null,
            'filename' => $header['filename'] ?? null,
        ];
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(WhatsAppCampaignService::class)->createCampaign($data, Auth::user());
    }

    protected function afterCreate(): void
    {
        if (! (bool) data_get($this->form->getRawState(), 'send_immediately', true)) {
            return;
        }

        try {
            app(WhatsAppCampaignService::class)->queueCampaign($this->record, Auth::user(), wait: false);
        } catch (\RuntimeException $exception) {
            Notification::make()
                ->title('Campaign saved but not sent')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return WhatsAppCampaignResource::getUrl('view', ['record' => $this->record]);
    }
}
