<?php

namespace App\Enums;

enum HomeworkContentType: string
{
    case Text = 'text';
    case Pdf = 'pdf';
    case Image = 'image';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text only',
            self::Pdf => 'PDF',
            self::Image => 'Image',
        };
    }
}
