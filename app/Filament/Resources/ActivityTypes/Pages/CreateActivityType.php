<?php

namespace App\Filament\Resources\ActivityTypes\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\ActivityTypes\ActivityTypeResource;
use App\Support\ActivityTypePresets;
use Filament\Resources\Pages\CreateRecord;

class CreateActivityType extends CreateRecord
{
    use ShowsCrmPageHint;

    protected static string $resource = ActivityTypeResource::class;

    protected static function crmHintKey(): ?string
    {
        return 'activity.types.create';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($this->tracksMarksEnabled($data)) {
            $data['field_schema'] = ActivityTypePresets::ensureMarksFields($data['field_schema'] ?? null);
        } else {
            $data['field_schema'] = ActivityTypePresets::stripMarksFields($data['field_schema'] ?? null);
        }

        unset($data['tracks_marks']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function tracksMarksEnabled(array $data): bool
    {
        return (bool) ($data['tracks_marks'] ?? true);
    }
}
