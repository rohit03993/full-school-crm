<?php

namespace App\Models;

use App\Enums\StaffLoginMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffLoginSession extends Model
{
    protected $fillable = [
        'user_id',
        'logged_in_at',
        'logged_out_at',
        'method',
        'ip_address',
        'user_agent',
        'session_key',
    ];

    protected function casts(): array
    {
        return [
            'logged_in_at' => 'datetime',
            'logged_out_at' => 'datetime',
            'method' => StaffLoginMethod::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOpen(): bool
    {
        return $this->logged_out_at === null;
    }

    public function durationLabel(): string
    {
        $end = $this->logged_out_at ?? now();
        $seconds = max(0, $this->logged_in_at?->diffInSeconds($end) ?? 0);

        if ($seconds < 60) {
            return $seconds.'s';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours > 0) {
            return $hours.'h '.$minutes.'m';
        }

        return $minutes.'m';
    }
}
