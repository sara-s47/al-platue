<?php

namespace App\Enums;

enum PointsTransactionType: string
{
    case Welcome = 'welcome';
    case Earn = 'earn';
    case CampaignAward = 'campaign_award';
    case ManualAdd = 'manual_add';
    case ManualDeduction = 'manual_deduction';
    case Redemption = 'redemption';
    case Expiration = 'expiration';
    case Refund = 'refund';
    case Reversal = 'reversal';
    case Adjustment = 'adjustment';
}
