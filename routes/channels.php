<?php

use App\Models\User;
use App\Services\Notification\AdminNotificationService;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('admin.{userId}', function (User $user, int $userId) {
    return (int) $user->id === $userId && $user->hasAnyRole(AdminNotificationService::ADMIN_ROLES);
});
