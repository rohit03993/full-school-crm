<?php

namespace App\Enums;

enum PracticalCategory: string
{
    case FrontOffice = 'front_office';
    case FoodProduction = 'food_production';
    case Housekeeping = 'housekeeping';
    case FnbService = 'fnb_service';

    public function label(): string
    {
        return match ($this) {
            self::FrontOffice => 'Front Office',
            self::FoodProduction => 'Food Production',
            self::Housekeeping => 'Housekeeping',
            self::FnbService => 'F&B Service',
        };
    }
}
