<?php

namespace App\Models;

use App\Enums\HomeworkContentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HomeworkAssignment extends Model
{
    protected $fillable = [
        'batch_id',
        'created_by_user_id',
        'title',
        'description',
        'content_type',
        'file_path',
        'published_at',
        'whatsapp_sent_count',
        'whatsapp_failed_count',
    ];

    protected function casts(): array
    {
        return [
            'content_type' => HomeworkContentType::class,
            'published_at' => 'datetime',
            'whatsapp_sent_count' => 'integer',
            'whatsapp_failed_count' => 'integer',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function views(): HasMany
    {
        return $this->hasMany(HomeworkView::class);
    }

    public function portalUrl(): string
    {
        return route('portal.homework.show', $this);
    }

    public function portalViewUrl(): ?string
    {
        if (! $this->hasFile()) {
            return null;
        }

        return route('portal.homework.view', $this);
    }

    public function staffPreviewUrl(): ?string
    {
        if (! $this->hasFile()) {
            return null;
        }

        return route('admin.homework.preview', $this);
    }

    public function staffDownloadUrl(): ?string
    {
        if (! $this->hasFile()) {
            return null;
        }

        return route('admin.homework.download', $this);
    }

    public function isPreviewable(): bool
    {
        return $this->hasFile() && in_array($this->content_type, [
            HomeworkContentType::Pdf,
            HomeworkContentType::Image,
        ], true);
    }

    public function inlineFileResponse(): StreamedResponse
    {
        abort_unless($this->hasFile(), 404);
        abort_unless(Storage::disk('public')->exists($this->file_path), 404);

        $filename = basename((string) $this->file_path);

        $response = Storage::disk('public')->response(
            (string) $this->file_path,
            $filename,
            ['Content-Type' => $this->previewMimeType()],
        );

        $response->headers->set('Content-Disposition', 'inline; filename="'.$filename.'"');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Cache-Control', 'private, max-age=3600');

        return $response;
    }

    public function downloadFileResponse(): StreamedResponse
    {
        abort_unless($this->hasFile(), 404);
        abort_unless(Storage::disk('public')->exists($this->file_path), 404);

        $extension = pathinfo((string) $this->file_path, PATHINFO_EXTENSION);

        return Storage::disk('public')->download(
            (string) $this->file_path,
            $this->title.($extension !== '' ? '.'.$extension : ''),
        );
    }

    public function previewMimeType(): string
    {
        if (filled($this->file_path) && Storage::disk('public')->exists($this->file_path)) {
            $detected = Storage::disk('public')->mimeType($this->file_path);

            if (is_string($detected) && $detected !== '') {
                return $detected;
            }
        }

        return match ($this->content_type) {
            HomeworkContentType::Pdf => 'application/pdf',
            HomeworkContentType::Image => 'image/jpeg',
            default => 'application/octet-stream',
        };
    }

    public function hasFile(): bool
    {
        return filled($this->file_path);
    }

    public function totalStudentsCount(): int
    {
        return (int) BatchStudent::query()
            ->where('batch_id', $this->batch_id)
            ->where('is_active', true)
            ->whereHas('student', fn ($query) => $query->whereNotNull('mobile')->where('mobile', '!=', ''))
            ->count();
    }

    public function viewedStudentsCount(): int
    {
        return $this->views()->count();
    }

    public function viewPercentage(): int
    {
        $total = $this->totalStudentsCount();

        if ($total < 1) {
            return 0;
        }

        return (int) round(($this->viewedStudentsCount() / $total) * 100);
    }
}
