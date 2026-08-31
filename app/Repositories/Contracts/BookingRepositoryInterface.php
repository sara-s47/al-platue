<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface BookingRepositoryInterface extends BaseRepositoryInterface
{
    public function getOverlappingForStudio(int $studioId, string $startAt, string $endAt, ?array $statuses = null): Collection;

    public function existsByBookingNumber(string $bookingNumber): bool;
}
