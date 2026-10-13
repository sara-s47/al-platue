<?php

namespace App\Services\Notification;

use App\Enums\UserStatus;
use App\Events\AdminNotificationCreated;
use App\Exceptions\BusinessException;
use App\Models\AdminNotification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Throwable;

class AdminNotificationService
{
    public const ADMIN_ROLES = ['super_admin', 'admin', 'staff'];

    /**
     * Stores one notification per admin (super admins always, others only with $permission)
     * and pushes it in real time on each admin's private channel.
     *
     * @param  array<string, mixed>  $data
     */
    public function notifyAdmins(string $type, string $title, string $body, array $data = [], ?string $permission = null): int
    {
        $recipients = $this->recipients($permission);

        foreach ($recipients as $admin) {
            $notification = AdminNotification::query()->create([
                'user_id' => $admin->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => $data,
            ]);

            try {
                event(new AdminNotificationCreated($notification));
            } catch (Throwable $e) {
                // A Pusher outage must not fail the action that triggered the notification.
                report($e);
            }
        }

        return $recipients->count();
    }

    public function listForAdmin(int $userId, bool $unreadOnly = false, int $perPage = 15): LengthAwarePaginator
    {
        return AdminNotification::query()
            ->where('user_id', $userId)
            ->when($unreadOnly, fn ($q) => $q->whereNull('read_at'))
            ->latest('id')
            ->paginate($perPage);
    }

    public function unreadCount(int $userId): int
    {
        return AdminNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function markRead(int $notificationId, int $userId): AdminNotification
    {
        $notification = AdminNotification::query()
            ->where('user_id', $userId)
            ->find($notificationId);

        if (! $notification) {
            throw new BusinessException('Notification not found.', 'notification_not_found', 404);
        }

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return $notification;
    }

    public function markAllRead(int $userId): int
    {
        return AdminNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);
    }

    /**
     * @return Collection<int, User>
     */
    protected function recipients(?string $permission): Collection
    {
        return User::query()
            ->role(self::ADMIN_ROLES)
            ->where('status', UserStatus::Active->value)
            ->get()
            ->filter(fn (User $user) => $permission === null
                || $user->hasRole('super_admin')
                || $user->checkPermissionTo($permission))
            ->values();
    }
}
