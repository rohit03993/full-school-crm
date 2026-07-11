<?php

namespace App\Filament\Resources\BiometricDevices\Pages;

use App\Filament\Resources\BiometricDevices\BiometricDeviceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBiometricDevice extends EditRecord
{
    protected static string $resource = BiometricDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['serial_number'] = strtoupper(trim((string) ($data['serial_number'] ?? '')));

        return $data;
    }
}
