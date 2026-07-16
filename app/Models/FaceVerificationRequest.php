<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaceVerificationRequest extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_PASS = 'PASS';

    public const STATUS_FAIL = 'FAIL';

    public const STATUS_TIMEOUT = 'TIMEOUT';

    public const STATUS_ERROR = 'ERROR';

    protected $fillable = [
        'id',
        'face_request_id',
        'biometric_punch_id',
        'biometric_device_id',
        'student_id',
        'enrollment_number',
        'face_student_id',
        'face_device_id',
        'status',
        'score',
        'punched_at',
        'requested_at',
        'responded_at',
        'error_message',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:6',
            'punched_at' => 'datetime',
            'requested_at' => 'datetime',
            'responded_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function biometricPunch(): BelongsTo
    {
        return $this->belongsTo(BiometricPunch::class);
    }

    public function biometricDevice(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
