<?php

namespace App\Services\Booking;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class CancellationService
{
    public function __construct()
    {
    }

    /**
     * @return array{refund_amount: float, refund_percentage: float, cancellation_fee: float, policy_id: int|null}
     */
    public function calculateRefund(Model $booking): array
    {
        $hoursUntilStart = Carbon::now()->diffInHours(Carbon::parse($booking->start_at), false);
        $paidAmount = (float) $booking->paid_amount;
        $totalAmount = (float) $booking->total_amount;

        $policies = \Illuminate\Support\Facades\DB::table('cancellation_policies')
            ->where('is_active', true)
            ->orderByDesc('hours_before')
            ->get();

        $applicable = $policies->first(fn ($policy) => $hoursUntilStart >= (int) $policy->hours_before);

        if (! $applicable) {
            return [
                'refund_amount' => 0,
                'refund_percentage' => 0,
                'cancellation_fee' => $totalAmount,
                'policy_id' => null,
            ];
        }

        $refundPercentage = (float) $applicable->refund_percentage;
        $cancellationFee = (float) $applicable->cancellation_fee;
        $refundAmount = max(0, round(($paidAmount * ($refundPercentage / 100)) - $cancellationFee, 2));

        return [
            'refund_amount' => $refundAmount,
            'refund_percentage' => $refundPercentage,
            'cancellation_fee' => $cancellationFee,
            'policy_id' => (int) $applicable->id,
        ];
    }
}
