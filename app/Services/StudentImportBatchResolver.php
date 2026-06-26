<?php

namespace App\Services;

use App\Models\Batch;
use Illuminate\Database\Eloquent\Collection;

class StudentImportBatchResolver
{
    /**
     * @var Collection<int, Batch>|null
     */
    protected ?Collection $cachedBatches = null;

    protected ?int $cachedSessionId = null;

    public function resolve(string $label, ?int $academicSessionId = null): ?Batch
    {
        $label = trim($label);

        if ($label === '') {
            return null;
        }

        $batches = $this->batchesForScope($academicSessionId);
        $normalizedInput = $this->normalizeLabel($label);

        $exactMatchers = [
            fn (Batch $batch): bool => trim($batch->name) === $label,
            fn (Batch $batch): bool => filled($batch->section) && trim((string) $batch->section) === $label,
            fn (Batch $batch): bool => $this->normalizeLabel($batch->name) === $normalizedInput,
            fn (Batch $batch): bool => filled($batch->section)
                && $this->normalizeLabel((string) $batch->section) === $normalizedInput,
        ];

        foreach ($exactMatchers as $matcher) {
            $match = $batches->first($matcher);

            if ($match) {
                return $match->loadMissing(['course', 'academicSession']);
            }
        }

        $partialMatches = $batches->filter(function (Batch $batch) use ($normalizedInput): bool {
            $normalizedName = $this->normalizeLabel($batch->name);

            if ($normalizedName === $normalizedInput) {
                return true;
            }

            if (str_contains($normalizedName, $normalizedInput) || str_contains($normalizedInput, $normalizedName)) {
                return true;
            }

            if (filled($batch->section)) {
                $normalizedSection = $this->normalizeLabel((string) $batch->section);

                return $normalizedSection === $normalizedInput
                    || str_contains($normalizedName, $normalizedSection)
                    && str_contains($normalizedInput, $normalizedSection);
            }

            return false;
        });

        if ($partialMatches->count() === 1) {
            return $partialMatches->first()->loadMissing(['course', 'academicSession']);
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function suggestions(string $label, ?int $academicSessionId = null, int $limit = 3): array
    {
        $label = trim($label);

        if ($label === '') {
            return [];
        }

        $normalizedInput = $this->normalizeLabel($label);

        return $this->batchesForScope($academicSessionId)
            ->sortBy('name')
            ->filter(function (Batch $batch) use ($normalizedInput): bool {
                $normalizedName = $this->normalizeLabel($batch->name);

                return str_contains($normalizedName, $normalizedInput)
                    || str_contains($normalizedInput, $normalizedName);
            })
            ->take($limit)
            ->map(fn (Batch $batch): string => $batch->name)
            ->values()
            ->all();
    }

    public function normalizeLabel(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        $value = preg_replace('/\s*\(\s*/', ' (', $value) ?? '';
        $value = preg_replace('/\s*\)\s*/', ')', $value) ?? '';
        $value = str_replace(['–', '—'], '-', $value);

        return trim($value);
    }

    /**
     * @return Collection<int, Batch>
     */
    protected function batchesForScope(?int $academicSessionId): Collection
    {
        if ($this->cachedBatches !== null && $this->cachedSessionId === $academicSessionId) {
            return $this->cachedBatches;
        }

        $this->cachedSessionId = $academicSessionId;
        $this->cachedBatches = Batch::query()
            ->with(['course', 'academicSession'])
            ->when(
                $academicSessionId,
                fn ($query) => $query->where('academic_session_id', $academicSessionId),
            )
            ->orderBy('name')
            ->get();

        return $this->cachedBatches;
    }
}
