<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\Admission;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentService
{
    public const DISK = 'local';

    public function __construct(
        protected StorageCleanupService $storage,
    ) {}

    public function store(
        Model $documentable,
        DocumentType $type,
        UploadedFile $file,
        ?User $uploader = null,
    ): Document {
        $studentId = $documentable instanceof Admission
            ? $documentable->student_id
            : $documentable->getKey();

        $extension = $file->getClientOriginalExtension() ?: $file->extension();
        $filename = Str::uuid().'.'.strtolower($extension);
        $path = "documents/{$studentId}/{$type->value}/{$filename}";

        Storage::disk(self::DISK)->putFileAs(
            "documents/{$studentId}/{$type->value}",
            $file,
            $filename,
        );

        $documentable->documents()
            ->where('type', $type->value)
            ->get()
            ->each(fn (Document $existing) => $this->deleteFile($existing));

        return $documentable->documents()->create([
            'type' => $type,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'uploaded_by_user_id' => $uploader?->id,
        ]);
    }

    public function storeFromFilamentUpload(
        Model $documentable,
        DocumentType $type,
        mixed $upload,
        ?User $uploader = null,
    ): Document {
        return $this->store($documentable, $type, $this->resolveUploadedFile($upload), $uploader);
    }

    protected function resolveUploadedFile(mixed $upload): UploadedFile
    {
        if ($upload instanceof UploadedFile) {
            return $upload;
        }

        if (is_array($upload)) {
            $upload = $upload[0] ?? null;
        }

        if (is_string($upload) && Storage::disk(self::DISK)->exists($upload)) {
            return new UploadedFile(
                Storage::disk(self::DISK)->path($upload),
                basename($upload),
                Storage::disk(self::DISK)->mimeType($upload) ?: null,
                null,
                true,
            );
        }

        throw \Illuminate\Validation\ValidationException::withMessages([
            'photo' => 'Please upload a valid JPG or PNG photo.',
        ]);
    }

    public function deleteFile(Document $document): void
    {
        $this->storage->deleteStoredFile($document->file_path);

        $document->delete();
    }

    public function hasRequiredDocuments(Admission $admission): bool
    {
        $uploaded = $admission->documents
            ->map(fn (Document $document) => $document->type->value)
            ->all();

        foreach (DocumentType::cases() as $type) {
            if ($type->isRequiredForAdmission() && ! in_array($type->value, $uploaded, true)) {
                return false;
            }
        }

        return true;
    }
}
