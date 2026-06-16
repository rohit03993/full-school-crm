<?php

namespace App\Models;

use App\Services\SiteImageService;
use Illuminate\Database\Eloquent\Model;

class SiteGalleryItem extends Model
{
    protected $fillable = [
        'image_path',
        'alt',
        'caption',
        'span_class',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function getImageUrlAttribute(): string
    {
        return SiteImageService::url($this->image_path) ?? '';
    }

    protected static function booted(): void
    {
        static::deleting(function (SiteGalleryItem $item): void {
            SiteImageService::delete($item->image_path);
        });
    }
}
