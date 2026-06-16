<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Enums\RoleName;
use App\Filament\Resources\Staff\StaffResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStaff extends EditRecord
{
    protected static string $resource = StaffResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['role'] = $this->record->roles->first()?->name ?? RoleName::Staff->value;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $role = $data['role'] ?? RoleName::Staff->value;
        unset($data['role']);

        $this->record->syncRoles([$role]);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
