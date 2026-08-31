<?php

namespace App\Repositories\Eloquent;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Repositories\Contracts\BookingRepositoryInterface;

class EloquentBookingRepository extends BaseRepository implements BookingRepositoryInterface
{
    public function __construct(Booking $model)
    {
        parent::__construct($model);
    }

    public function getOverlappingForStudio(int $studioId, string $startAt, string $endAt, ?array $statuses = null): \Illuminate\Support\Collection
    {
        $statuses ??= [
            BookingStatus::Pending->value,
            BookingStatus::Confirmed->value,
            BookingStatus::InProgress->value,
            BookingStatus::Extended->value,
        ];

        return $this->model->newQuery()
            ->where('studio_id', $studioId)
            ->whereIn('status', $statuses)
            ->where('start_at', '<', $endAt)
            ->where('end_at', '>', $startAt)
            ->get();
    }

    public function existsByBookingNumber(string $bookingNumber): bool
    {
        return $this->model->newQuery()
            ->where('booking_number', $bookingNumber)
            ->exists();
    }
}
