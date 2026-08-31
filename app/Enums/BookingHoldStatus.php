<?php

namespace App\Enums;

enum BookingHoldStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Converted = 'converted';
    case Cancelled = 'cancelled';
}
