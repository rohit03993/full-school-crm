<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\Student;
use App\Services\DocumentService;
use App\Services\StorageCleanupService;
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

        $this->artisan('crm:check-documents', ['--scope' => 'documents'])
            ->expectsOutputToContain('Files missing on disk')
            ->expectsOutputToContain($missing->file_path)
            ->assertSuccessful();
    }

    public function test_it_reports_a_clean_bill_when_all_files_exist(): void
    {
        Storage::fake(DocumentService::DISK);

        $document = $this->document('documents/1/photo/present.jpg');
        Storage::disk(DocumentService::DISK)->put($document->file_path, 'image-bytes');

        $this->artisan('crm:check-documents', ['--scope' => 'documents'])
            ->expectsOutputToContain('Every checked path that has a database value also has a file on disk')
            ->assertSuccessful();
    }

    public function test_orphan_cleanup_no_longer_deletes_id_cards_or_receipts(): void
    {
        Storage::fake(StorageCleanupService::DISK);
        Storage::disk(StorageCleanupService::DISK)->put('id_cards/orphan.pdf', 'x');
        Storage::disk(StorageCleanupService::DISK)->put('receipts/orphan.pdf', 'x');
        Storage::disk(StorageCleanupService::DISK)->put('payments/orphan.jpg', 'x');

        $deleted = app(StorageCleanupService::class)->pruneOrphanStoredFiles();

        $this->assertSame(0, $deleted);
        Storage::disk(StorageCleanupService::DISK)->assertExists('id_cards/orphan.pdf');
        Storage::disk(StorageCleanupService::DISK)->assertExists('receipts/orphan.pdf');
        Storage::disk(StorageCleanupService::DISK)->assertExists('payments/orphan.jpg');
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
