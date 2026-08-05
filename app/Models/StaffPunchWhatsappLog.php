<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffPunchWhatsappLog extends Model
{
    protected $fillable = [
        'user_id',
        'employee_code',
        'state',
        'punch_date',
        'punch_time',
        'phone',
        'status',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'punch_date' => 'date',
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
