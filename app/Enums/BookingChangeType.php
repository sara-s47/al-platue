<?php

namespace App\Enums;

enum BookingChangeType: string
{
    case Reschedule = 'reschedule';
    case Extension = 'extension';
    case Overtime = 'overtime';
    case Cancellation = 'cancellation';
}
