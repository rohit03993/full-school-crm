<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BiometricPunch extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_MIRRORED = 'mirrored';

    public const STATUS_FAILED = 'failed';

    public const STATUS_IGNORED = 'ignored';

    protected $fillable = [
        'biometric_device_id',
        'serial_number',
        'user_pin',
        'punched_at',
        'punch_status',
        'verify_type',
        'work_code',
        'punch_log_id',
        'process_status',
        'process_error',
        'raw_line',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'punched_at' => 'datetime',
            'punch_status' => 'integer',
            'verify_type' => 'integer',
            'raw_payload' => 'array',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'biometric_device_id');
    }

    public function faceVerificationRequest(): HasOne
    {
        return $this->hasOne(FaceVerificationRequest::class);
    }
}
