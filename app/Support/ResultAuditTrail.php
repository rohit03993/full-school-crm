<?php

namespace App\Support;

use App\Models\ActivitySession;
use App\Models\AuditLog;
use App\Models\ResultDeclaration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ResultAuditTrail
{
    /**
     * @return Collection<int, AuditLog>
     */
    public static function entriesForGroupKey(string $groupKey): Collection
    {
        if (! Schema::hasTable('audit_logs') || blank($groupKey)) {
            return collect();
        }

        $declaration = PublishedResultsGate::declarationForGroupKey($groupKey);
        $sessionIds = ActivitySession::query()
            ->where('metadata->test_key', $groupKey)
            ->pluck('id')
            ->all();

        return AuditLog::query()
            ->with('user')
            ->where(function ($query) use ($declaration, $sessionIds, $groupKey): void {
                if ($declaration) {
                    $query->orWhere(function ($inner) use ($declaration): void {
                        $inner->where('auditable_type', ResultDeclaration::class)
                            ->where('auditable_id', $declaration->id);
                    });
                }

                if ($sessionIds !== []) {
                    $query->orWhere(function ($inner) use ($sessionIds): void {
                        $inner->where('auditable_type', ActivitySession::class)
                            ->whereIn('auditable_id', $sessionIds);
                    });
                }

                $query->orWhere(function ($inner) use ($groupKey): void {
                    $inner->where('action', 'activity_marks_imported')
                        ->where('new_values->test_key', $groupKey);
                });

                $query->orWhere(function ($inner) use ($groupKey): void {
                    $inner->where('action', 'marks_changed_after_publish')
                        ->where('new_values->group_key', $groupKey);
                });
            })
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    public static function labelForAction(string $action): string
    {
        return match ($action) {
            'result_published' => 'Results published',
            'result_unpublished' => 'Results unpublished',
            'marks_locked' => 'Marks locked',
            'marks_unlocked' => 'Marks unlocked',
            'marksheets_issued' => 'PDF marksheets issued',
            'marks_changed_after_publish' => 'Marks changed after publish',
            'activity_attendance_marked' => 'Marks entered / updated',
            'activity_marks_imported' => 'Marks imported from Excel',
            default => str_replace('_', ' ', ucfirst($action)),
        };
    }
}
