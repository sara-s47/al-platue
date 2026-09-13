<?php

namespace App\Services\Notification;

use App\Enums\BookingStatus;
use App\Enums\NotificationRecipientStatus;
use App\Enums\NotificationStatus;
use App\Exceptions\BusinessException;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Services\Promotion\SegmentationService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function __construct(
        protected NotificationRepositoryInterface $notificationRepository,
        protected BookingRepositoryInterface $bookingRepository,
        protected FirebasePushService $firebasePushService,
        protected SegmentationService $segmentationService,
    ) {
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->notificationRepository->paginate($perPage);
    }

    public function create(array $data, ?array $userIds = null, ?int $createdBy = null): Model
    {
        return DB::transaction(function () use ($data, $userIds, $createdBy) {
            $notification = $this->notificationRepository->create([
                'title' => $data['title'],
                'body' => $data['body'],
                'type' => $data['type'],
                'deep_link' => $data['deep_link'] ?? null,
                'status' => NotificationStatus::Draft->value,
                'created_by' => $createdBy,
            ]);

            if ($userIds) {
                $this->createRecipients($notification->id, $userIds);
            }

            return $this->sendNow($notification->id);
        });
    }

    public function sendNow(int $notificationId): Model
    {
        return DB::transaction(function () use ($notificationId) {
            $notification = $this->notificationRepository->findOrFail($notificationId);

            if (in_array($notification->status, [
                NotificationStatus::Sent,
                NotificationStatus::Cancelled,
            ], true)) {
                throw new BusinessException('Notification cannot be sent in its current state.', 'notification_not_sendable');
            }

            $this->notificationRepository->update($notificationId, [
                'status' => NotificationStatus::Sending->value,
            ]);

            $recipients = DB::table('notification_recipients')
                ->where('notification_id', $notificationId)
                ->get();

            if ($recipients->isEmpty()) {
                throw new BusinessException('Notification has no recipients.', 'notification_no_recipients');
            }

            $userIds = $recipients->pluck('user_id')->map(fn ($id) => (int) $id)->all();
            $result = $this->firebasePushService->sendToUsers(
                $userIds,
                $notification->title,
                $notification->body,
                $this->buildPushData($notification),
            );

            $now = now();
            $failed = (int) ($result['failed'] ?? 0);

            foreach ($recipients as $recipient) {
                DB::table('notification_recipients')
                    ->where('id', $recipient->id)
                    ->update([
                        'status' => $failed > 0
                            ? NotificationRecipientStatus::Failed->value
                            : NotificationRecipientStatus::Sent->value,
                        'sent_at' => $now,
                        'delivered_at' => $failed > 0 ? null : $now,
                        'updated_at' => $now,
                    ]);
            }

            return $this->notificationRepository->update($notificationId, [
                'status' => $failed > 0
                    ? NotificationStatus::Failed->value
                    : NotificationStatus::Sent->value,
            ]);
        });
    }

    public function schedule(int $notificationId, Carbon $scheduledAt): Model
    {
        $notification = $this->notificationRepository->findOrFail($notificationId);

        if ($scheduledAt->isPast()) {
            throw new BusinessException('Scheduled time must be in the future.', 'invalid_schedule_time');
        }

        return $this->notificationRepository->update($notificationId, [
            'scheduled_at' => $scheduledAt,
            'status' => NotificationStatus::Scheduled->value,
        ]);
    }

    public function sendToSegment(int $segmentId, array $data, ?int $createdBy = null): Model
    {
        if (! $createdBy && empty($data['created_by'])) {
            throw new BusinessException('Notification creator is required.', 'created_by_required');
        }

        return DB::transaction(function () use ($segmentId, $data, $createdBy) {
            $userIds = $this->segmentationService->getUsersForSegment($segmentId)
                ->map(fn ($id) => (int) $id)
                ->all();

            $notification = $this->create(array_merge($data, [
                'type' => $data['type'] ?? 'segment',
                'created_by' => $createdBy ?? $data['created_by'],
            ]), $userIds);

            return $this->sendNow($notification->id);
        });
    }

    public function markAsRead(int $notificationId, int $userId): void
    {
        $updated = DB::table('notification_recipients')
            ->where('notification_id', $notificationId)
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        if (! $updated) {
            throw new BusinessException('Notification not found for this user.', 'notification_not_found', 404);
        }
    }

    public function listForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->notificationRepository->listForUser($userId, $perPage);
    }

    public function sendBookingConfirmation(int $bookingId): Model
    {
        $booking = $this->bookingRepository->findOrFail($bookingId);

        if (! in_array($booking->status, [
            BookingStatus::Confirmed->value,
            BookingStatus::Pending->value,
        ], true)) {
            throw new BusinessException('Booking is not eligible for confirmation notification.', 'booking_not_confirmable');
        }

        $notification = $this->create([
            'title' => 'Booking Confirmed',
            'body' => "Your booking {$booking->booking_number} has been confirmed.",
            'type' => 'booking_confirmation',
            'deep_link' => "bookings/{$booking->id}",
            'created_by' => (int) $booking->user_id,
        ], [(int) $booking->user_id]);

        return $this->sendNow($notification->id);
    }

    public function sendBookingReminder(int $bookingId): Model
    {
        $booking = $this->bookingRepository->findOrFail($bookingId);

        if ($booking->status !== BookingStatus::Confirmed) {
            throw new BusinessException('Only confirmed bookings can receive reminders.', 'booking_not_remindable');
        }

        $notification = $this->create([
            'title' => 'Upcoming Booking Reminder',
            'body' => "Reminder: your booking {$booking->booking_number} starts at {$booking->start_at}.",
            'type' => 'booking_reminder',
            'deep_link' => "bookings/{$booking->id}",
            'created_by' => (int) $booking->user_id,
        ], [(int) $booking->user_id]);

        return $this->sendNow($notification->id);
    }

    public function processDueScheduled(): int
    {
        $notifications = $this->notificationRepository->findDueScheduled();
        $processed = 0;

        foreach ($notifications as $notification) {
            $this->sendNow($notification->id);
            $processed++;
        }

        return $processed;
    }

    /**
     * @param  array<int, int>  $userIds
     */
    protected function createRecipients(int $notificationId, array $userIds): void
    {
        $now = now();
        $rows = [];

        foreach (array_unique($userIds) as $userId) {
            $rows[] = [
                'notification_id' => $notificationId,
                'user_id' => $userId,
                'status' => NotificationRecipientStatus::Pending->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('notification_recipients')->insert($rows);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function buildPushData(Model $notification): array
    {
        return array_filter([
            'type' => $notification->type,
            'deep_link' => $notification->deep_link,
            'notification_id' => (string) $notification->id,
        ]);
    }
}
