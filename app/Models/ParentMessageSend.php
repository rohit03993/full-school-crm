<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentMessageSend extends Model
{
    public const Homework = 'homework';

    public const ExamMarks = 'exam_marks';

    protected $fillable = [
        'kind',
        'is_resend',
        'batch_id',
        'homework_date',
        'test_key',
        'label',
        'sent_by_user_id',
        'parent_count',
        'whatsapp_campaign_id',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'is_resend' => 'boolean',
            'homework_date' => 'date',
            'parent_count' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            self::Homework => 'Homework',
            self::ExamMarks => 'Exam marks',
            default => 'Message',
        };
    }
}
