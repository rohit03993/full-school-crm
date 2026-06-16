<?php

namespace App\Filament\Resources\IndustrialVisits\Pages;

use App\Filament\Resources\IndustrialVisits\IndustrialVisitResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateIndustrialVisit extends CreateRecord
{
    protected static string $resource = IndustrialVisitResource::class;

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
