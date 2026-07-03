<?php

namespace App\Enums;

enum CampusVisitPurpose: string
{
    case Fees = 'fees';
    case Academic = 'academic';
    case Documents = 'documents';
    case General = 'general';
    case Complaint = 'complaint';

    public function label(): string
    {
        return match ($this) {
            self::Fees => 'Fees / payment',
            self::Academic => 'Academic doubt',
            self::Documents => 'Documents',
            self::General => 'General query',
            self::Complaint => 'Complaint / issue',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
