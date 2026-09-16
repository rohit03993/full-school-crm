<?php

namespace Tests\Unit;

use App\Services\ActivityMarksImportColumnMapper;
use Tests\TestCase;

class ActivityMarksImportColumnMapperTest extends TestCase
{
    public function test_guesses_pcm_and_z_as_subjects(): void
    {
        $mapping = app(ActivityMarksImportColumnMapper::class)->guess([
            'Roll No',
            'Name',
            'P',
            'C',
            'M',
            'Z',
            'Total',
            'Rank',
        ]);

        $this->assertSame(0, $mapping['roll_column']);
        $this->assertSame([2, 3, 4, 5], $mapping['subject_columns']);
    }
}
