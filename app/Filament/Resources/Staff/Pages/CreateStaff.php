<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Enums\RoleName;
use App\Filament\Resources\Staff\StaffResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStaff extends CreateRecord
{
    protected static string $resource = StaffResource::class;

    protected ?string $role = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->role = $data['role'] ?? RoleName::Staff->value;
        unset($data['role']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->syncRoles([$this->role ?? RoleName::Staff->value]);

        if (! $this->record->staffProfile()->exists()) {
            $this->record->staffProfile()->create([]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
