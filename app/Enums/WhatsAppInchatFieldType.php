<?php

namespace App\Enums;

enum WhatsAppInchatFieldType: string
{
    case ShortText = 'short_text';
    case Note = 'note';
    case Date = 'date';
    case Choices = 'choices';
    case CourseList = 'course_list';

    public function label(): string
    {
        return match ($this) {
            self::ShortText => 'Short text',
            self::Note => 'Note',
            self::Date => 'Date',
            self::Choices => 'Choices',
            self::CourseList => 'Course list',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
