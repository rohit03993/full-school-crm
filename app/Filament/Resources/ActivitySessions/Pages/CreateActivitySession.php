<?php

namespace App\Filament\Resources\ActivitySessions\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Pages\ActivityAttendancePage;
use App\Filament\Resources\ActivitySessions\ActivitySessionResource;
use App\Models\ActivityType;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateActivitySession extends CreateRecord
{
    use ShowsCrmPageHint;

    protected static string $resource = ActivitySessionResource::class;

    protected static function crmHintKey(): ?string
    {
        return 'activity.sessions.create';
    }

    public function mount(): void
    {
        parent::mount();

        $preset = request()->query('type');

        if (! filled($preset)) {
            return;
        }

        $activityTypeId = ActivityType::query()
            ->where('slug', (string) $preset)
            ->where('is_enabled', true)
            ->value('id');

        if ($activityTypeId) {
            $this->form->fill([
                'activity_type_id' => $activityTypeId,
            ]);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = Auth::id();
        $data['metadata'] = collect($data['metadata'] ?? [])
            ->filter(fn (mixed $value): bool => filled($value))
            ->all();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        $record = $this->getRecord();

        if ($record && ! $record->activityType?->supportsScoring()) {
            return ActivityAttendancePage::getUrl().'?id='.$record->id;
        }

        return $this->getResource()::getUrl('index');
    }
}
