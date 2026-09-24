<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeworkStudentLink extends Model
{
    protected $fillable = [
        'homework_assignment_id',
        'student_id',
        'token',
        'click_count',
        'first_clicked_at',
        'last_clicked_at',
    ];

    protected function casts(): array
    {
        return [
            'click_count' => 'integer',
            'first_clicked_at' => 'datetime',
            'last_clicked_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(HomeworkAssignment::class, 'homework_assignment_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function publicUrl(): string
    {
        return route('homework.public.show', ['token' => $this->token]);
    }

    public function recordOpen(): void
    {
        $this->forceFill([
            'first_clicked_at' => $this->first_clicked_at ?? now(),
            'last_clicked_at' => now(),
            'click_count' => ((int) $this->click_count) + 1,
        ])->save();
    }
}
