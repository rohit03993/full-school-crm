<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    public function log(
        string $action,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?User $user = null,
    ): AuditLog {
        $user ??= Auth::user();

        $userName = $user?->name ?? 'Website Visitor';
        $userRole = $user instanceof User ? $user->primaryRoleLabel() : 'Public';

        return AuditLog::query()->create([
            'user_id' => $user?->id,
            'user_name' => $userName,
            'user_role' => $userRole,
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => $reason,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
