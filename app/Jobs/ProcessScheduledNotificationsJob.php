<?php

namespace App\Jobs;

use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessScheduledNotificationsJob implements ShouldQueue
{
    use Queueable;

    public function handle(NotificationService $notificationService): void
    {
        $notificationService->processDueScheduled();
    }
}
