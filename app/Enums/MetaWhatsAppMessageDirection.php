<?php

namespace App\Enums;

enum MetaWhatsAppMessageDirection: string
{
    case Outbound = 'outbound';
    case Inbound = 'inbound';
}
