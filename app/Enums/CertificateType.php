<?php

namespace App\Enums;

enum CertificateType: string
{
    case Transfer = 'transfer';
    case Bonafide = 'bonafide';
    case Character = 'character';
    case Birth = 'birth';
    case Fee = 'fee';

    public function label(): string
    {
        return match ($this) {
            self::Transfer => 'Transfer certificate',
            self::Bonafide => 'Bonafide certificate',
            self::Character => 'Character certificate',
            self::Birth => 'Birth certificate',
            self::Fee => 'Fee certificate',
        };
    }

    public function documentTitle(): string
    {
        return match ($this) {
            self::Transfer => 'Transfer Certificate',
            self::Bonafide => 'Bonafide Certificate',
            self::Character => 'Character Certificate',
            self::Birth => 'Birth Certificate',
            self::Fee => 'Fee Certificate',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
