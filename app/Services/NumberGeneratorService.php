<?php

namespace App\Services;

use App\Enums\NumberSequenceType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class NumberGeneratorService
{
    public function generate(NumberSequenceType|string $type, ?int $year = null): string
    {
        $sequenceType = $type instanceof NumberSequenceType
            ? $type
            : NumberSequenceType::from($type);

        $year ??= (int) now()->format('Y');

        $nextNumber = DB::transaction(function () use ($sequenceType, $year): int {
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $sequence = DB::table('number_sequences')
                    ->where('type', $sequenceType->value)
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->first();

                if ($sequence) {
                    $nextNumber = $sequence->last_number + 1;

                    DB::table('number_sequences')
                        ->where('id', $sequence->id)
                        ->update([
                            'last_number' => $nextNumber,
                            'updated_at' => now(),
                        ]);

                    return $nextNumber;
                }

                try {
                    DB::table('number_sequences')->insert([
                        'type' => $sequenceType->value,
                        'year' => $year,
                        'last_number' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    return 1;
                } catch (QueryException $exception) {
                    if ($attempt === 2) {
                        throw $exception;
                    }
                }
            }

            return 1;
        });

        return sprintf(
            '%s-%d-%06d',
            $sequenceType->prefix(),
            $year,
            $nextNumber,
        );
    }
}
