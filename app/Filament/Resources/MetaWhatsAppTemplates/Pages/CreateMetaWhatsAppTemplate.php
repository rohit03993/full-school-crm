<?php

namespace App\Filament\Resources\MetaWhatsAppTemplates\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\MetaWhatsAppTemplates\MetaWhatsAppTemplateResource;
use App\Services\MetaWhatsAppTemplateSubmitService;
use App\Support\MetaWhatsAppTemplateVariableHelper;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

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

        // Use raw state — getState() validates and can fail on an empty create form.
        $state = $this->form->getRawState();
        $bodyText = (string) ($state['body_text'] ?? '');

        if ($bodyText === '') {
            return;
        }

        $this->form->fill([
            ...$state,
            'body_variable_samples' => MetaWhatsAppTemplateVariableHelper::syncRowsFromBody(
                $bodyText,
                is_array($state['body_variable_samples'] ?? null) ? $state['body_variable_samples'] : [],
                (string) ($state['name'] ?? ''),
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
            $message = $exception->getMessage();

            Notification::make()
                ->title('Could not submit template')
                ->body($message)
                ->danger()
                ->persistent()
                ->send();

            throw ValidationException::withMessages([
                'data.body_text' => $message,
                'body_text' => $message,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Could not submit template')
                ->body($exception->getMessage() ?: 'Unexpected error while submitting to Meta.')
                ->danger()
                ->persistent()
                ->send();

            throw new Halt;
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
