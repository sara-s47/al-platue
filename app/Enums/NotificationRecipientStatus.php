<?php

namespace App\Enums;

enum NotificationRecipientStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
}
