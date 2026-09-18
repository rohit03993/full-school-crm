<?php

namespace App\Support;

use App\Enums\ExamWindowStatus;
use App\Filament\Pages\ExamWindowPage;
use App\Models\ExamWindow;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * How marks were added for an exam group — teacher-entry window vs Excel — for hub badges.
 * Does not create, update, or delete any records.
 */
final class ExamMarksPath
{
    /**
     * @param  list<string>  $groupKeys
     * @return array<string, array{mode: 'teachers'|'excel', label: string, color: string, window_id: ?int, url: ?string}>
     */
    public static function forGroupKeys(array $groupKeys): array
    {
        $keys = array_values(array_filter($groupKeys, fn (string $key): bool => $key !== ''));

        if ($keys === [] || ! Schema::hasTable('exam_windows')) {
            return [];
        }

        $windows = ExamWindow::query()
            ->whereIn('test_key', $keys)
            ->get(['id', 'test_key', 'status'])
            ->keyBy('test_key');

        $meta = [];

        foreach ($keys as $key) {
            $window = $windows->get($key);

            if (! $window instanceof ExamWindow) {
                $meta[$key] = [
                    'mode' => 'excel',
                    'label' => 'Excel upload',
                    'color' => 'gray',
                    'window_id' => null,
                    'url' => null,
                ];

                continue;
            }

            $status = $window->status instanceof ExamWindowStatus
                ? $window->status
                : ExamWindowStatus::tryFrom((string) $window->status);

            $url = null;

            try {
                $url = ExamWindowPage::getUrl(['window' => $window->id]);
            } catch (Throwable) {
                $url = null;
            }

            $meta[$key] = [
                'mode' => 'teachers',
                'label' => $status?->label() ?? 'Teachers entering',
                'color' => $status?->color() ?? 'info',
                'window_id' => $window->id,
                'url' => $url,
            ];
        }

        return $meta;
    }
}
