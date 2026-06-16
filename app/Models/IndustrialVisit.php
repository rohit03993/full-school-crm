<?php

namespace App\Models;

use App\Models\Concerns\HasActivityAttendance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndustrialVisit extends Model
{
    use HasActivityAttendance;

    protected $fillable = [
        'name',
        'location',
        'visit_date',
        'description',
        'batch_id',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
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
        return $this->name.' · '.$this->batch?->name.' · '.$this->visit_date->format('d M Y');
    }
}
