<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BiometricDevice extends Model
{
    protected $fillable = [
        'name',
        'serial_number',
        'location',
        'is_active',
        'requires_face_verify',
        'face_verify_device_id',
        'attlog_stamp',
        'operlog_stamp',
        'last_seen_at',
        'last_punch_at',
        'today_punch_count',
        'today_punch_count_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'requires_face_verify' => 'boolean',
            'last_seen_at' => 'datetime',
            'last_punch_at' => 'datetime',
            'today_punch_count_date' => 'date',
            'today_punch_count' => 'integer',
        ];
    }

    public function punches(): HasMany
    {
        return $this->hasMany(BiometricPunch::class);
    }

    public function faceVerificationRequests(): HasMany
    {
        return $this->hasMany(FaceVerificationRequest::class);
    }

    public function touchSeen(?string $attlogStamp = null, ?string $operlogStamp = null): void
    {
        $payload = ['last_seen_at' => now()];

        if ($attlogStamp !== null && $attlogStamp !== '') {
            $payload['attlog_stamp'] = $attlogStamp;
        }

        if ($operlogStamp !== null && $operlogStamp !== '') {
            $payload['operlog_stamp'] = $operlogStamp;
        }

        $this->forceFill($payload)->save();
    }

    public function recordPunchReceived(): void
    {
        $today = now()->toDateString();
        $count = $this->today_punch_count_date?->toDateString() === $today
            ? ((int) $this->today_punch_count) + 1
            : 1;

        $this->forceFill([
            'last_seen_at' => now(),
            'last_punch_at' => now(),
            'today_punch_count' => $count,
            'today_punch_count_date' => $today,
        ])->save();
    }
}
