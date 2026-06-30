<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentMarksheet extends Model
{
    protected $fillable = [
        'result_declaration_id',
        'student_id',
        'marksheet_serial',
        'total_obtained',
        'total_max',
        'percentage',
        'division',
        'pdf_path',
        'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'total_obtained' => 'decimal:2',
            'total_max' => 'decimal:2',
            'percentage' => 'decimal:2',
            'snapshot' => 'array',
            'marksheet_serial' => 'integer',
        ];
    }

    public function resultDeclaration(): BelongsTo
    {
        return $this->belongsTo(ResultDeclaration::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function hasPdf(): bool
    {
        return filled($this->pdf_path);
    }

    public function formattedSerial(): string
    {
        $offset = (int) config('marksheet.serial_display_offset', 1);

        return str_pad((string) ($this->marksheet_serial + $offset), 8, '0', STR_PAD_LEFT);
    }
}
