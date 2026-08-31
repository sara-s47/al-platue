<?php

namespace App\Repositories\Eloquent;

use App\Enums\BookingHoldStatus;
use App\Models\BookingHold;
use App\Repositories\Contracts\BookingHoldRepositoryInterface;

class EloquentBookingHoldRepository extends BaseRepository implements BookingHoldRepositoryInterface
{
    public function __construct(BookingHold $model)
    {
        parent::__construct($model);
    }

    public function getActiveOverlappingForStudio(int $studioId, string $startAt, string $endAt): \Illuminate\Support\Collection
    {
        return $this->model->newQuery()
            ->where('studio_id', $studioId)
            ->where('status', BookingHoldStatus::Active->value)
            ->where('expires_at', '>', now())
            ->where('start_at', '<', $endAt)
            ->where('end_at', '>', $startAt)
            ->get();
    }

    public function expirePast(): int
    {
        return $this->model->newQuery()
            ->where('status', BookingHoldStatus::Active->value)
            ->where('expires_at', '<=', now())
            ->update(['status' => BookingHoldStatus::Expired->value]);
    }
}
