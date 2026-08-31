<?php

namespace App\Enums;

enum LoyaltyRuleType: string
{
    case Welcome = 'welcome';
    case Booking = 'booking';
    case Spending = 'spending';
    case Redemption = 'redemption';
    case Expiration = 'expiration';
}
