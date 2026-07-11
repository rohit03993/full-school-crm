<?php

namespace App\Filament\Resources\BiometricDevices\Pages;

use App\Filament\Resources\BiometricDevices\BiometricDeviceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBiometricDevice extends CreateRecord
{
    protected static string $resource = BiometricDeviceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['serial_number'] = strtoupper(trim((string) ($data['serial_number'] ?? '')));

        return $data;
    }
}
