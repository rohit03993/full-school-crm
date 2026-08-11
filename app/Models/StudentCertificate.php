<?php

namespace App\Models;

use App\Enums\CertificateType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentCertificate extends Model
{
    protected $fillable = [
        'student_id',
        'enrollment_id',
        'type',
        'serial_number',
        'serial',
        'issued_on',
        'issued_by_user_id',
        'remarks',
        'pdf_path',
        'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'type' => CertificateType::class,
            'issued_on' => 'date',
            'serial' => 'integer',
            'snapshot' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function hasPdf(): bool
    {
        return filled($this->pdf_path);
    }
}
