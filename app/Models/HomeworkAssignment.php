<?php

namespace App\Models;

use App\Enums\HomeworkContentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

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

    public function fileUrl(): ?string
    {
        if (blank($this->file_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->file_path);
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
