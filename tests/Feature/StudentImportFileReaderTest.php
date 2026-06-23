<?php

namespace Tests\Feature;

use App\Services\StudentImportFileReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentImportFileReaderTest extends TestCase
{
    public function test_store_and_parse_reads_csv_from_default_disk_root(): void
    {
        Storage::fake('local');

        $csv = "Roll Number,Student Name,Father Name,Mobile\n101,Test Student,Test Parent,9876500101\n";
        $upload = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $result = app(StudentImportFileReader::class)->storeAndParse($upload);

        $this->assertSame('Roll Number', $result['headers'][0]);
        $this->assertSame('101', $result['rows'][0][0]);
        Storage::disk('local')->assertExists($result['path']);
        $this->assertFileExists(Storage::disk('local')->path($result['path']));
    }
}
