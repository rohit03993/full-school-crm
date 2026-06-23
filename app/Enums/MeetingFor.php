<?php

namespace App\Enums;

enum MeetingFor: string
{
    case Enquiry = 'enquiry';
    case Admission = 'admission';
    case Marketing = 'marketing';
    case Fees = 'fees';
    case General = 'general';

    /** @deprecated Legacy institute-segment values — kept for old records */
    case School = 'school';
    /** @deprecated Legacy institute-segment values — kept for old records */
    case Coaching = 'coaching';
    /** @deprecated Legacy institute-segment values — kept for old records */
    case College = 'college';

    public function label(): string
    {
        return match ($this) {
            self::Enquiry => 'Enquiry',
            self::Admission => 'Admission',
            self::Marketing => 'Marketing',
            self::Fees => 'Fees',
            self::General => 'General',
            self::School => 'School',
            self::Coaching => 'Coaching',
            self::College => 'College',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Enquiry => 'heroicon-m-chat-bubble-left-right',
            self::Admission => 'heroicon-m-document-check',
            self::Marketing => 'heroicon-m-megaphone',
            self::Fees => 'heroicon-m-banknotes',
            self::General => 'heroicon-m-question-mark-circle',
            self::School => 'heroicon-m-building-library',
            self::Coaching => 'heroicon-m-academic-cap',
            self::College => 'heroicon-m-building-office-2',
        };
    }

    /**
     * @return array{bg: string, text: string, ring: string}
     */
    public function badgeColors(): array
    {
        return match ($this) {
            self::Enquiry => [
                'bg' => 'bg-sky-500/20',
                'text' => 'text-sky-900 dark:text-sky-300',
                'ring' => 'ring-sky-500/35',
            ],
            self::Admission => [
                'bg' => 'bg-emerald-500/20',
                'text' => 'text-emerald-900 dark:text-emerald-300',
                'ring' => 'ring-emerald-500/35',
            ],
            self::Marketing => [
                'bg' => 'bg-violet-500/20',
                'text' => 'text-violet-900 dark:text-violet-300',
                'ring' => 'ring-violet-500/35',
            ],
            self::Fees => [
                'bg' => 'bg-amber-500/20',
                'text' => 'text-amber-900 dark:text-amber-300',
                'ring' => 'ring-amber-500/35',
            ],
            self::General => [
                'bg' => 'bg-gray-500/20',
                'text' => 'text-gray-900 dark:text-gray-300',
                'ring' => 'ring-gray-500/35',
            ],
            self::School => [
                'bg' => 'bg-amber-500/20',
                'text' => 'text-amber-900 dark:text-amber-300',
                'ring' => 'ring-amber-500/35',
            ],
            self::Coaching => [
                'bg' => 'bg-violet-500/20',
                'text' => 'text-violet-900 dark:text-violet-300',
                'ring' => 'ring-violet-500/35',
            ],
            self::College => [
                'bg' => 'bg-sky-500/20',
                'text' => 'text-sky-900 dark:text-sky-300',
                'ring' => 'ring-sky-500/35',
            ],
        };
    }

    public function isFormOption(): bool
    {
        return in_array($this, self::formCases(), true);
    }

    /**
     * @return array<int, self>
     */
    public static function formCases(): array
    {
        return [
            self::Enquiry,
            self::Admission,
            self::Marketing,
            self::Fees,
            self::General,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function formOptions(): array
    {
        return collect(self::formCases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }

    public static function defaultForForms(): self
    {
        return self::Enquiry;
    }
}
