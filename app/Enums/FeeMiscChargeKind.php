<?php

namespace App\Enums;

enum FeeMiscChargeKind: string
{
    case Bundled = 'bundled';
    case Separate = 'separate';
    case GstPenalty = 'gst_penalty';
    case LateFeePenalty = 'late_fee_penalty';

    public function label(): string
    {
        return match ($this) {
            self::Bundled => 'Included in fee plan',
            self::Separate => 'Additional charge',
            self::GstPenalty => 'GST penalty',
            self::LateFeePenalty => 'Late fee penalty',
        };
    }
}
