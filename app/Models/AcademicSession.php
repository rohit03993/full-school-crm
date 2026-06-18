<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicSession extends Model
{
    protected $fillable = [
        'name',
        'code',
        'starts_on',
        'ends_on',
        'is_current',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_current' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (AcademicSession $session): void {
            if ($session->is_current) {
                static::query()
                    ->whereKeyNot($session->id)
                    ->update(['is_current' => false]);
            }
        });
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function selectLabel(): string
    {
        return $this->name.($this->is_current ? ' (current)' : '');
    }

    public static function current(): ?self
    {
        return static::query()
            ->where('is_current', true)
            ->where('is_active', true)
            ->first();
    }
}
