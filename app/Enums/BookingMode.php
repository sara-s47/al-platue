<?php

namespace App\Enums;

enum BookingMode: string
{
    case Hourly = 'hourly';
    case Daily = 'daily';
}
