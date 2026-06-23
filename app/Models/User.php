<?php

namespace App\Models;

use App\Enums\RoleName;
use App\Support\CrmAccess;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'mobile',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return CrmAccess::hasPanelAccess($this);
    }

    public function canCrm(\App\Enums\CrmPermission|string $permission): bool
    {
        return CrmAccess::can($this, $permission);
    }

    /**
     * @return list<string>
     */
    public function staffJobRoleLabels(): array
    {
        return collect(CrmAccess::jobRoleNamesFor($this))
            ->map(fn (string $name): string => \App\Enums\StaffJobRole::from($name)->label())
            ->all();
    }

    public function primaryRoleLabel(): string
    {
        $role = $this->roles->first();

        return $role?->name
            ? RoleName::tryFrom($role->name)?->label() ?? $role->name
            : 'Unknown';
    }

    public function staffCollectorLabel(): string
    {
        $code = $this->staffProfile?->employee_code;

        if (filled($code)) {
            return "{$code} · {$this->name}";
        }

        return $this->name;
    }
}
