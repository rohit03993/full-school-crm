<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\Staff\StaffResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStaff extends CreateRecord
{
    use ShowsCrmPageHint;

    protected static string $resource = StaffResource::class;

    protected static function crmHintKey(): ?string
    {
        return 'staff.create';
    }

    /**
     * @var list<string>
     */
    protected array $rolesToSync = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->rolesToSync = $this->resolveRolesToSync($data);
        unset($data['job_roles'], $data['is_super_admin']);
        $data['email'] = null;

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->syncRoles($this->rolesToSync);

        if (! $this->record->staffProfile()->exists()) {
            $this->record->staffProfile()->create([]);
        }
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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
