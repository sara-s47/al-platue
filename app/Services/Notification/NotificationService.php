<?php

namespace App\Services\Notification;

use App\Enums\BookingStatus;
use App\Enums\NotificationAudience;
use App\Enums\NotificationCategory;
use App\Enums\NotificationRecipientStatus;
use App\Enums\NotificationStatus;
use App\Enums\UserStatus;
use App\Exceptions\BusinessException;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Services\Promotion\SegmentationService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * @return array{categories: list<array{value: string, label: string}>, audiences: list<array{value: string, label: string}>}
     */
    public function options(): array
    {
        return [
            'categories' => NotificationCategory::options(),
            'audiences' => NotificationAudience::options(),
        ];
    }

    /**
     * @param  array<int, int>|null  $userIds
     */
    public function previewAudience(string $audience, ?array $userIds = null): int
    {
        return count($this->resolveAudienceUserIds($audience, $userIds));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>|null  $userIds
     */
    public function create(array $data, ?array $userIds = null, ?int $createdBy = null): Model
    {
        return DB::transaction(function () use ($data, $userIds, $createdBy) {
            $type = $data['category'] ?? $data['type'] ?? NotificationCategory::General->value;
            $audience = $data['audience'] ?? null;
            $explicitIds = $userIds ?? $data['user_ids'] ?? null;

            if ($audience !== null) {
                $resolvedIds = $this->resolveAudienceUserIds((string) $audience, is_array($explicitIds) ? $explicitIds : null);
            } else {
                $resolvedIds = array_values(array_unique(array_map('intval', (array) $explicitIds)));
            }

            if ($resolvedIds === []) {
                throw new BusinessException('Notification has no recipients.', 'notification_no_recipients');
            }

            $notification = $this->notificationRepository->create([
                'title' => $data['title'],
                'body' => $data['body'],
                'type' => $type,
                'deep_link' => $data['deep_link'] ?? null,
                'status' => NotificationStatus::Draft->value,
                'created_by' => $createdBy ?? ($data['created_by'] ?? null),
            ]);

            $this->createRecipients($notification->id, $resolvedIds);

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

        $userIds = $this->segmentationService->getUsersForSegment($segmentId)
            ->map(fn ($id) => (int) $id)
            ->all();

        return $this->create(array_merge($data, [
            'type' => $data['type'] ?? 'segment',
            'created_by' => $createdBy ?? $data['created_by'],
        ]), $userIds, $createdBy ?? ($data['created_by'] ?? null));
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

        $status = $booking->status instanceof BookingStatus
            ? $booking->status
            : BookingStatus::tryFrom((string) $booking->status);

        if (! in_array($status, [BookingStatus::Confirmed, BookingStatus::Pending], true)) {
            throw new BusinessException('Booking is not eligible for confirmation notification.', 'booking_not_confirmable');
        }

        return $this->create([
            'title' => 'Booking Confirmed',
            'body' => "Your booking {$booking->booking_number} has been confirmed.",
            'type' => 'booking_confirmation',
            'deep_link' => "bookings/{$booking->id}",
            'created_by' => (int) $booking->user_id,
        ], [(int) $booking->user_id], (int) $booking->user_id);
    }

    public function sendBookingReminder(int $bookingId): Model
    {
        $booking = $this->bookingRepository->findOrFail($bookingId);

        if ($booking->status !== BookingStatus::Confirmed) {
            throw new BusinessException('Only confirmed bookings can receive reminders.', 'booking_not_remindable');
        }

        return $this->create([
            'title' => 'Upcoming Booking Reminder',
            'body' => "Reminder: your booking {$booking->booking_number} starts at {$booking->start_at}.",
            'type' => 'booking_reminder',
            'deep_link' => "bookings/{$booking->id}",
            'created_by' => (int) $booking->user_id,
        ], [(int) $booking->user_id], (int) $booking->user_id);
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
     * @param  array<int, int>|null  $userIds
     * @return list<int>
     */
    public function resolveAudienceUserIds(string $audience, ?array $userIds = null): array
    {
        $audienceEnum = NotificationAudience::tryFrom($audience);

        if (! $audienceEnum) {
            throw new BusinessException('Invalid notification audience.', 'invalid_audience');
        }

        return match ($audienceEnum) {
            NotificationAudience::All => $this->activeCustomerQuery()->pluck('users.id')->map(fn ($id) => (int) $id)->all(),
            NotificationAudience::WithBookings => $this->activeCustomerQuery()
                ->whereHas('bookings')
                ->pluck('users.id')
                ->map(fn ($id) => (int) $id)
                ->all(),
            NotificationAudience::WithoutBookings => $this->activeCustomerQuery()
                ->whereDoesntHave('bookings')
                ->pluck('users.id')
                ->map(fn ($id) => (int) $id)
                ->all(),
            NotificationAudience::Custom => $this->resolveCustomCustomerIds($userIds ?? []),
        };
    }

    /**
     * @param  array<int, int>  $userIds
     * @return list<int>
     */
    protected function resolveCustomCustomerIds(array $userIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $userIds)));

        if ($ids === []) {
            throw new BusinessException('user_ids are required for custom audience.', 'user_ids_required');
        }

        $validIds = $this->activeCustomerQuery()
            ->whereIn('users.id', $ids)
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($validIds === []) {
            throw new BusinessException('No valid active customers found in user_ids.', 'notification_no_recipients');
        }

        return $validIds;
    }

    protected function activeCustomerQuery(): Builder
    {
        return User::query()
            ->role('customer')
            ->where('users.status', UserStatus::Active->value);
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
