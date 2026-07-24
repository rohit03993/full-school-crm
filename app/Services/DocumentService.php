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
use Illuminate\Validation\ValidationException;
use League\Flysystem\UnableToWriteFile;
use Throwable;

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

        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin'));
        $filename = Str::uuid().'.'.$extension;
        $directory = "documents/{$studentId}/{$type->value}";
        $path = "{$directory}/{$filename}";
        $disk = Storage::disk(self::DISK);

        try {
            $stored = $disk->putFileAs($directory, $file, $filename);
        } catch (UnableToWriteFile|Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                $type->value => 'Could not save the file to storage. Check that storage/app/private is writable, then try again.',
            ]);
        }

        if ($stored === false || ! $disk->exists($path)) {
            throw ValidationException::withMessages([
                $type->value => 'Could not save the file to storage. Check that storage/app/private is writable, then try again.',
            ]);
        }

        // Only remove previous files after the new file is confirmed on disk.
        $documentable->documents()
            ->where('type', $type->value)
            ->get()
            ->each(fn (Document $existing) => $this->deleteFile($existing));

        return $documentable->documents()->create([
            'type' => $type,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName() ?: $filename,
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

        throw ValidationException::withMessages([
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
