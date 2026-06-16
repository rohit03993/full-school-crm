<?php

namespace App\Filament\Resources\PracticalSessions\Pages;

use App\Filament\Resources\PracticalSessions\PracticalSessionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreatePracticalSession extends CreateRecord
{
    protected static string $resource = PracticalSessionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = Auth::id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
