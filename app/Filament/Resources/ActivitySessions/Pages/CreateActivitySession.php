<?php

namespace App\Filament\Resources\ActivitySessions\Pages;

use App\Filament\Resources\ActivitySessions\ActivitySessionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateActivitySession extends CreateRecord
{
    protected static string $resource = ActivitySessionResource::class;

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
        return $this->getResource()::getUrl('index');
    }
}
