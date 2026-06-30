<?php

namespace App\Support;

use App\Enums\ResultDeclarationStatus;
use App\Models\ActivityAttendance;
use App\Models\ActivitySession;
use App\Models\ResultDeclaration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class PublishedResultsGate
{
    /**
     * @return array<string, ResultDeclarationStatus>
     */
    public static function statusesByGroupKey(): array
    {
        if (! Schema::hasTable('result_declarations')) {
            return [];
        }

        return ResultDeclaration::query()
            ->get(['group_key', 'status', 'declared_at'])
            ->filter(fn (ResultDeclaration $row): bool => $row->isPublished())
            ->mapWithKeys(fn (ResultDeclaration $row): array => [
                $row->group_key => $row->status,
            ])
            ->all();
    }

    public static function isPublishedGroupKey(string $groupKey): bool
    {
        if (! Schema::hasTable('result_declarations')) {
            return false;
        }

        return ResultDeclaration::query()
            ->where('group_key', $groupKey)
            ->where('status', ResultDeclarationStatus::Published)
            ->whereNotNull('declared_at')
            ->exists();
    }

    /**
     * @param  Collection<int, ActivityAttendance>  $records
     * @return Collection<int, ActivityAttendance>
     */
    public static function filterRecordsForPortal(Collection $records): Collection
    {
        $publishedKeys = array_keys(self::statusesByGroupKey());

        if ($publishedKeys === []) {
            return collect();
        }

        return $records->filter(function (ActivityAttendance $record) use ($publishedKeys): bool {
            $session = $record->attendable;

            if (! $session instanceof ActivitySession) {
                return false;
            }

            return in_array(StudentExamMarksMatrix::groupKeyForSession($session), $publishedKeys, true);
        })->values();
    }

    /**
     * @param  array{subjects: list<string>, rows: list<array<string, mixed>>}  $matrix
     * @return array{subjects: list<string>, rows: list<array<string, mixed>>}
     */
    public static function filterMatrixForPortal(array $matrix, string $groupKeyPrefix = ''): array
    {
        $publishedKeys = self::statusesByGroupKey();

        if ($publishedKeys === []) {
            return ['subjects' => [], 'rows' => []];
        }

        $rows = collect($matrix['rows'] ?? [])
            ->filter(function (array $row) use ($publishedKeys): bool {
                foreach (array_keys($publishedKeys) as $groupKey) {
                    $label = $row['label'] ?? '';

                    if (str_contains($groupKey, (string) $label) || isset($row['group_key'])) {
                        return isset($publishedKeys[$row['group_key'] ?? '']);
                    }
                }

                return false;
            })
            ->values()
            ->all();

        return [
            'subjects' => $matrix['subjects'] ?? [],
            'rows' => $rows,
        ];
    }
}
