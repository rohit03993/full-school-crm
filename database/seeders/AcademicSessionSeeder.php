<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use Illuminate\Database\Seeder;

class AcademicSessionSeeder extends Seeder
{
    public function run(): void
    {
        $year = (int) now()->format('Y');
        $month = (int) now()->format('n');

        if ($month >= 4) {
            $startYear = $year;
            $endYear = $year + 1;
        } else {
            $startYear = $year - 1;
            $endYear = $year;
        }

        $code = "{$startYear}-".substr((string) $endYear, -2);
        $name = "{$startYear}–{$endYear}";

        AcademicSession::query()->updateOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'starts_on' => "{$startYear}-04-01",
                'ends_on' => "{$endYear}-03-31",
                'is_current' => true,
                'is_active' => true,
            ],
        );
    }
}
