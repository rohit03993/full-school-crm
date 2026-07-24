<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Services\DocumentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'type',
        'file_path',
        'original_filename',
        'uploaded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
        ];
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function isImage(): bool
    {
        return in_array($this->extension(), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }

    public function isPreviewableInBrowser(): bool
    {
        return in_array($this->extension(), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'], true);
    }

    public function extension(): string
    {
        $fromOriginal = strtolower((string) pathinfo((string) $this->original_filename, PATHINFO_EXTENSION));

        if ($fromOriginal !== '') {
            return $fromOriginal;
        }

        return strtolower((string) pathinfo((string) $this->file_path, PATHINFO_EXTENSION));
    }

    public function fileExistsOnDisk(): bool
    {
        if (blank($this->file_path)) {
            return false;
        }

        return Storage::disk(DocumentService::DISK)->exists($this->file_path);
    }

    public function downloadUrl(): string
    {
        return route('admin.documents.download', $this);
    }

    public function previewUrl(): string
    {
        return route('admin.documents.preview', $this);
    }
}
