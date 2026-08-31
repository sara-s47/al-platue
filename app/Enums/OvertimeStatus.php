<?php

namespace App\Enums;

enum OvertimeStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Outstanding = 'outstanding';
}
