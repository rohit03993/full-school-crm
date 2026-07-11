<?php

namespace App\Filament\Resources\BiometricDevices\Pages;

use App\Filament\Resources\BiometricDevices\BiometricDeviceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBiometricDevices extends ListRecords
{
    protected static string $resource = BiometricDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
