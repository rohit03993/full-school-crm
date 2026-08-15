<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\Student;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CrmCheckDocumentsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_documents_whose_file_is_gone(): void
    {
        Storage::fake(DocumentService::DISK);

        $present = $this->document('documents/1/photo/present.jpg');
        Storage::disk(DocumentService::DISK)->put($present->file_path, 'image-bytes');

        $missing = $this->document('documents/1/photo/deleted.jpg');

        $this->artisan('crm:check-documents')
            ->expectsOutputToContain('Files missing on disk')
            ->expectsOutputToContain($missing->file_path)
            ->assertSuccessful();
    }

    public function test_it_reports_a_clean_bill_when_all_files_exist(): void
    {
        Storage::fake(DocumentService::DISK);

        $document = $this->document('documents/1/photo/present.jpg');
        Storage::disk(DocumentService::DISK)->put($document->file_path, 'image-bytes');

        $this->artisan('crm:check-documents')
            ->expectsOutputToContain('Every document file is present')
            ->assertSuccessful();
    }

    private function document(string $path): Document
    {
        return Document::create([
            'documentable_type' => Student::class,
            'documentable_id' => 1,
            'type' => DocumentType::Photo->value,
            'file_path' => $path,
            'original_filename' => basename($path),
        ]);
    }
}
