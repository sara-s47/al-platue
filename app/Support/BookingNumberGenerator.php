<?php

namespace App\Support;

use App\Repositories\Contracts\BookingRepositoryInterface;

class BookingNumberGenerator
{
    public function __construct(
        protected BookingRepositoryInterface $bookingRepository,
    ) {
    }

    public function generate(): string
    {
        do {
            $number = 'BK-'.now()->format('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        } while ($this->bookingRepository->existsByBookingNumber($number));

        return $number;
    }
}
