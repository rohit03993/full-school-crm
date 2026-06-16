<?php

namespace App\Models;

use App\Enums\SeminarType;
use App\Models\Concerns\HasActivityAttendance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Seminar extends Model
{
    use HasActivityAttendance;

    protected $fillable = [
        'type',
        'title',
        'seminar_date',
        'batch_id',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => SeminarType::class,
            'seminar_date' => 'date',
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

    public function displayTitle(): string
    {
        return $this->title.' · '.$this->batch?->name.' · '.$this->seminar_date->format('d M Y');
    }
}
