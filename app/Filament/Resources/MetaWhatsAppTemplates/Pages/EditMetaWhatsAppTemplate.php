<?php

namespace App\Filament\Resources\MetaWhatsAppTemplates\Pages;

use App\Filament\Resources\MetaWhatsAppTemplates\MetaWhatsAppTemplateResource;
use App\Models\MetaWhatsAppTemplate;
use App\Services\MetaWhatsAppTemplateUpdateService;
use App\Support\MetaWhatsAppTemplateVariableHelper;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class EditMetaWhatsAppTemplate extends EditRecord
{
    protected static string $resource = MetaWhatsAppTemplateResource::class;

    protected bool $wordingSentForApproval = false;

    protected function getHeaderActions(): array
    {
        return [
            MetaWhatsAppTemplateResource::deleteTemplateAction(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = MetaWhatsAppTemplateResource::expandMappings($data);
        $data['body_text'] = (string) ($data['body'] ?? '');
        /** @var MetaWhatsAppTemplate $record */
        $record = $this->getRecord();
        $data['body_variable_samples'] = MetaWhatsAppTemplateResource::sampleRowsForEdit($record);
        $data['send_wording_for_approval'] = false;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $samples = $data['body_variable_samples'] ?? null;

        if (is_array($samples) && $samples !== []) {
            return MetaWhatsAppTemplateResource::normalizeCreateFormData($data);
        }

        $data = MetaWhatsAppTemplateResource::normalizeMappings($data);

        if (MetaWhatsAppTemplateVariableHelper::variableCount((string) ($data['body_text'] ?? '')) === 0) {
            $data['param_mappings'] = [];
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var MetaWhatsAppTemplate $record */
        $this->wordingSentForApproval = false;
        $newBody = trim((string) ($data['body_text'] ?? $record->body));
        $oldBody = trim((string) $record->body);
        $mappings = array_values($data['param_mappings'] ?? []);
        $sendWording = (bool) ($data['send_wording_for_approval'] ?? false);

        try {
            if ($newBody !== $oldBody) {
                if (! $sendWording) {
                    throw new InvalidArgumentException('The message words changed. Turn on "Send the new words to WhatsApp for approval", then save. Nothing was sent.');
                }

                $examples = is_array($data['body_examples'] ?? null) ? $data['body_examples'] : [];

                $record = app(MetaWhatsAppTemplateUpdateService::class)->reviseWording(
                    $record,
                    $newBody,
                    $examples,
                    $mappings,
                );
                $this->wordingSentForApproval = true;

                return $record;
            }

            $isActive = array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : (bool) $record->is_active;

            if (strtoupper((string) $record->status) !== 'APPROVED') {
                $isActive = (bool) $record->is_active;
            }

            $record->update([
                'param_mappings' => $mappings,
                'is_active' => $isActive,
            ]);
        } catch (InvalidArgumentException $exception) {
            Notification::make()
                ->title('Could not update template')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();

            throw ValidationException::withMessages([
                'data.body_text' => $exception->getMessage(),
                'body_text' => $exception->getMessage(),
            ]);
        }

        return $record;
    }

    protected function afterSave(): void
    {
        /** @var MetaWhatsAppTemplate $record */
        $record = $this->getRecord();
        MetaWhatsAppTemplateResource::mirrorMappingsToWhatsAppTemplate($record->refresh());
    }

    protected function getSavedNotification(): ?Notification
    {
        if ($this->wordingSentForApproval) {
            return Notification::make()
                ->success()
                ->title('Sent to WhatsApp for approval')
                ->body('The name is the same. Sending with this template pauses until the status is Approved. Then click Sync from Meta.');
        }

        return parent::getSavedNotification();
    }
}
