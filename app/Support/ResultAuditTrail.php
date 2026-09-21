<?php

namespace App\Support;

use App\Models\ActivitySession;
use App\Models\AuditLog;
use App\Models\ResultDeclaration;
use App\Services\ActivityMarksWhatsAppService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ResultAuditTrail
{
    /**
     * @return Collection<int, ResultAuditTrailEntry>
     */
    public static function entriesForGroupKey(string $groupKey): Collection
    {
        if (blank($groupKey)) {
            return collect();
        }

        $logs = self::auditLogEntries($groupKey);
        $whatsapp = self::whatsappEntries($groupKey);

        return $logs
            ->concat($whatsapp)
            ->sortByDesc(fn (ResultAuditTrailEntry $entry): int => $entry->created_at?->getTimestamp() ?? 0)
            ->values();
    }

    /**
     * @return Collection<int, ResultAuditTrailEntry>
     */
    protected static function auditLogEntries(string $groupKey): Collection
    {
        if (! Schema::hasTable('audit_logs')) {
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
            ->get()
            ->map(function (AuditLog $log): ResultAuditTrailEntry {
                $createdAt = $log->created_at instanceof Carbon
                    ? $log->created_at
                    : ($log->created_at ? Carbon::parse($log->created_at) : null);

                return new ResultAuditTrailEntry(
                    action: (string) $log->action,
                    created_at: $createdAt,
                    user_name: (string) ($log->user_name ?? $log->user?->name ?? 'System'),
                    detail: $log->action === 'marks_changed_after_publish'
                        ? 'marks updated after publish'
                        : null,
                );
            });
    }

    /**
     * @return Collection<int, ResultAuditTrailEntry>
     */
    protected static function whatsappEntries(string $groupKey): Collection
    {
        $history = app(ActivityMarksWhatsAppService::class)->classSheetSendHistory($groupKey);

        return collect($history['sends'] ?? [])
            ->map(function (array $send): ResultAuditTrailEntry {
                $at = filled($send['at_iso'] ?? null)
                    ? Carbon::parse($send['at_iso'])
                    : null;

                return new ResultAuditTrailEntry(
                    action: 'marks_whatsapp_sent',
                    created_at: $at,
                    user_name: (string) ($send['staff_name'] ?? 'Staff'),
                    detail: (string) ($send['result_line'] ?? ''),
                );
            });
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
            'marks_whatsapp_sent' => 'WhatsApp marks sent',
            default => str_replace('_', ' ', ucfirst($action)),
        };
    }
}
