<?php

namespace App\Enums;

enum PaymentType: string
{
    case Full = 'full';
    case Deposit = 'deposit';
    case Balance = 'balance';
    case Overtime = 'overtime';
    case Extension = 'extension';
}
