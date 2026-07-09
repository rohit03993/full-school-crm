<?php

namespace App\Filament\Resources\ActivityTypes\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\ActivityTypes\ActivityTypeResource;
use App\Models\ActivityType;
use App\Support\ActivityTypePresets;
use Filament\Resources\Pages\EditRecord;

class EditActivityType extends EditRecord
{
    use ShowsCrmPageHint;

    protected static string $resource = ActivityTypeResource::class;

    protected static function crmHintKey(): ?string
    {
        return 'activity.types.edit';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        $data['tracks_marks'] = $record instanceof ActivityType
            ? $record->supportsScoring()
            : false;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
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
        return (bool) ($data['tracks_marks'] ?? false);
    }
}
