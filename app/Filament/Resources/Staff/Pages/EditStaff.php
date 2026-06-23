<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\Staff\StaffResource;
use App\Support\CrmAccess;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStaff extends EditRecord
{
    use ShowsCrmPageHint;

    protected static string $resource = StaffResource::class;

    protected static function crmHintKey(): ?string
    {
        return 'staff.edit';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['is_super_admin'] = $this->record->hasRole(RoleName::SuperAdmin->value);
        $data['job_roles'] = CrmAccess::jobRoleNamesFor($this->record);

        if ($data['job_roles'] === [] && $this->record->hasRole(RoleName::Staff->value) && ! $data['is_super_admin']) {
            $data['job_roles'] = [];
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->record->syncRoles($this->resolveRolesToSync($data));
        unset($data['job_roles'], $data['is_super_admin']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    protected function resolveRolesToSync(array $data): array
    {
        if (! empty($data['is_super_admin'])) {
            return [RoleName::SuperAdmin->value];
        }

        $jobRoles = collect($data['job_roles'] ?? [])
            ->filter(fn (mixed $role): bool => filled($role))
            ->map(fn (mixed $role): string => (string) $role)
            ->filter(fn (string $role): bool => StaffJobRole::tryFrom($role) !== null)
            ->unique()
            ->values()
            ->all();

        if ($jobRoles === []) {
            return [RoleName::Staff->value];
        }

        return $jobRoles;
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
