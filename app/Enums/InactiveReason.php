<?php

namespace App\Enums;

enum InactiveReason: string
{
    case Fees = 'fees';
    case Academics = 'academics';
    case Hostel = 'hostel';
    case Travel = 'travel';
    case Health = 'health';
    case FamilyShifted = 'family_shifted';
    case JoinedAnotherSchool = 'joined_another_school';
    case ParentRequest = 'parent_request';
    case Behaviour = 'behaviour';
    case StoppedComing = 'stopped_coming';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Fees => 'Fees',
            self::Academics => 'Academics',
            self::Hostel => 'Hostel',
            self::Travel => 'Travel',
            self::Health => 'Health',
            self::FamilyShifted => 'Family shifted',
            self::JoinedAnotherSchool => 'Joined another school',
            self::ParentRequest => 'Parent request',
            self::Behaviour => 'Behaviour',
            self::StoppedComing => 'Stopped coming',
            self::Other => 'Other',
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
