<?php

namespace App\Jobs;

use App\Services\Studio\HoldService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireBookingHoldsJob implements ShouldQueue
{
    use Queueable;

    public function handle(HoldService $holdService): void
    {
        $holdService->expireHolds();
    }
}
