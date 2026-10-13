<?php

namespace App\Events;

use App\Http\Resources\Api\V1\AdminNotificationResource;
use App\Models\AdminNotification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class AdminNotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public AdminNotification $notification) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.'.$this->notification->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'admin.notification';
    }

    public function broadcastWith(): array
    {
        return (new AdminNotificationResource($this->notification))->resolve();
    }
}
