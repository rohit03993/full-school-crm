<?php

namespace App\Filament\Resources\MetaWhatsAppTemplates\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\MetaWhatsAppTemplates\MetaWhatsAppTemplateResource;
use App\Services\MetaWhatsAppTemplateSubmitService;
use App\Support\MetaWhatsAppTemplateVariableHelper;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CreateMetaWhatsAppTemplate extends CreateRecord
{
    use ShowsCrmPageHint;

    protected static string $resource = MetaWhatsAppTemplateResource::class;

    protected static ?string $title = 'Submit template to Meta';

    protected static function crmHintKey(): ?string
    {
        return 'whatsapp.templates.create';
    }

    public function mount(): void
    {
        parent::mount();

        $state = $this->form->getState();
        $bodyText = (string) ($state['body_text'] ?? '');

        if ($bodyText === '') {
            return;
        }

        $this->form->fill([
            ...$state,
            'body_variable_samples' => MetaWhatsAppTemplateVariableHelper::syncRowsFromBody(
                $bodyText,
                $state['body_variable_samples'] ?? [],
            ),
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return MetaWhatsAppTemplateResource::normalizeCreateFormData($data);
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(MetaWhatsAppTemplateSubmitService::class)->submit($data);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'body_text' => $exception->getMessage(),
            ]);
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Template submitted to Meta';
    }

    protected function getRedirectUrl(): string
    {
        return MetaWhatsAppTemplateResource::getUrl('index');
    }
}
