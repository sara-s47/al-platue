<?php

namespace App\Enums;

enum PricingRuleType: string
{
    case Base = 'base';
    case Weekend = 'weekend';
    case Peak = 'peak';
    case DateSpecific = 'date_specific';
}
