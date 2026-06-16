<?php

namespace App\Enums;

use App\Models\IndustrialVisit;
use App\Models\PracticalSession;
use App\Models\Seminar;
use Illuminate\Database\Eloquent\Model;

enum ActivityKind: string
{
    case Practical = 'practical';
    case IndustrialVisit = 'industrial_visit';
    case Seminar = 'seminar';

    public function label(): string
    {
        return match ($this) {
            self::Practical => 'Practical',
            self::IndustrialVisit => 'Industrial Visit',
            self::Seminar => 'Seminar',
        };
    }

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Practical => PracticalSession::class,
            self::IndustrialVisit => IndustrialVisit::class,
            self::Seminar => Seminar::class,
        };
    }

    public static function tryFromModel(Model $model): ?self
    {
        return match ($model::class) {
            PracticalSession::class => self::Practical,
            IndustrialVisit::class => self::IndustrialVisit,
            Seminar::class => self::Seminar,
            default => null,
        };
    }
}
