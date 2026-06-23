<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ActivityType extends Model
{
    protected $fillable = [
        'name',
        'plural_name',
        'slug',
        'icon',
        'description',
        'field_schema',
        'sort_order',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'field_schema' => 'array',
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ActivityType $type): void {
            if (blank($type->slug) && filled($type->name)) {
                $type->slug = Str::slug($type->name);
            }

            if (blank($type->plural_name) && filled($type->name)) {
                $type->plural_name = Str::plural($type->name);
            }
        });
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ActivitySession::class);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return array<int, array{key: string, label: string, type: string, required?: bool}>
     */
    public function fields(): array
    {
        return collect($this->field_schema ?? [])
            ->filter(fn (mixed $field): bool => is_array($field) && filled($field['key'] ?? null) && filled($field['label'] ?? null))
            ->values()
            ->all();
    }

    public function supportsScoring(): bool
    {
        return collect($this->fields())
            ->contains(fn (array $field): bool => ($field['key'] ?? null) === 'max_marks');
    }

    public function scopeAttendanceOnly(Builder $query): Builder
    {
        return $query->enabled()->ordered();
    }

    /**
     * Exam types without marks (Workshop, Event, etc.) — attendance per session.
     *
     * @return array<int, string>
     */
    public static function attendanceTypeOptions(): array
    {
        return static::query()
            ->attendanceOnly()
            ->get()
            ->reject(fn (ActivityType $type): bool => $type->supportsScoring())
            ->pluck('name', 'id')
            ->all();
    }
}
