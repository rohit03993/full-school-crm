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

    public function test_guesses_motion_roll_no_instead_of_serial_number(): void
    {
        $mapping = app(ActivityMarksImportColumnMapper::class)->guess([
            'S.No.',
            'Roll No',
            'Batch',
            'Name',
            'P',
            'C',
            'M',
            'Mark Obtain',
            'Percent',
        ]);

        $this->assertSame(1, $mapping['roll_column']);
        $this->assertSame([4, 5, 6], $mapping['subject_columns']);
    }

    public function test_guesses_r_no_as_roll_column(): void
    {
        $mapping = app(ActivityMarksImportColumnMapper::class)->guess([
            'R.No',
            'Name',
            'Physics',
        ]);

        $this->assertSame(0, $mapping['roll_column']);
        $this->assertSame([2], $mapping['subject_columns']);
    }

    public function test_roll_column_index_zero_is_not_treated_as_missing(): void
    {
        $missing = app(ActivityMarksImportColumnMapper::class)->missingRequiredFields([
            'roll_column' => 0,
            'subject_columns' => [1],
        ]);

        $this->assertSame([], $missing);
    }
}
