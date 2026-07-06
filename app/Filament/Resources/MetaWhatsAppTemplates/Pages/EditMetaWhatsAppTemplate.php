<?php

namespace App\Filament\Resources\MetaWhatsAppTemplates\Pages;

use App\Filament\Resources\MetaWhatsAppTemplates\MetaWhatsAppTemplateResource;
use Filament\Resources\Pages\EditRecord;

class EditMetaWhatsAppTemplate extends EditRecord
{
    protected static string $resource = MetaWhatsAppTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return MetaWhatsAppTemplateResource::expandMappings($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return MetaWhatsAppTemplateResource::normalizeMappings($data);
    }

    protected function afterSave(): void
    {
        MetaWhatsAppTemplateResource::mirrorMappingsToWhatsAppTemplate($this->record->refresh());
    }
}
