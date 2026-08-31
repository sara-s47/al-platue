<?php

namespace App\Enums;

enum CampaignUserStatus: string
{
    case Pending = 'pending';
    case Awarded = 'awarded';
    case Failed = 'failed';
    case Excluded = 'excluded';
}
