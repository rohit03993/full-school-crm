<?php

namespace App\Enums;

enum CallStatus: string
{
    case Connected = 'connected';
    case NoAnswer = 'no_answer';
    case Busy = 'busy';
    case SwitchedOff = 'switched_off';
    case NotReachable = 'not_reachable';
    case WrongNumber = 'wrong_number';
    case Callback = 'callback';

    public function label(): string
    {
        return match ($this) {
            self::Connected => 'Connected',
            self::NoAnswer => 'No Answer',
            self::Busy => 'Busy',
            self::SwitchedOff => 'Switched Off',
            self::NotReachable => 'Not Reachable',
            self::WrongNumber => 'Wrong Number',
            self::Callback => 'Callback Requested',
        };
    }

    public function isConnected(): bool
    {
        return $this === self::Connected;
    }

    /**
     * @return list<string>
     */
    public static function notConnectedValues(): array
    {
        return array_map(
            fn (self $status): string => $status->value,
            array_filter(self::cases(), fn (self $status): bool => $status !== self::Connected),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function notConnectedOptions(): array
    {
        return collect(self::cases())
            ->filter(fn (self $status): bool => $status !== self::Connected)
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])
            ->all();
    }
}
