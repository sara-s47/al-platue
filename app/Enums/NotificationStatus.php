<?php

namespace App\Enums;

enum NotificationStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
