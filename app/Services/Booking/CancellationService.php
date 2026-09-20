<?php

namespace App\Services\Booking;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CancellationService
{
    /**
     * Pick the strictest (highest hours_before) policy the customer still qualifies for.
     *
     * Example tiers: 72h→100%, 48h→50%, 24h→25%. Cancelling with 60h notice matches 48h.
     *
     * @return array{refund_amount: float, refund_percentage: float, cancellation_fee: float, policy_id: int|null, hours_until_start: float}
     */
    public function calculateRefund(Model $booking): array
    {
        $hoursUntilStart = Carbon::now()->floatDiffInHours(Carbon::parse($booking->start_at), false);
        $paidAmount = (float) $booking->paid_amount;
        $totalAmount = (float) $booking->total_amount;

        if ($hoursUntilStart < 0) {
            return [
                'refund_amount' => 0,
                'refund_percentage' => 0,
                'cancellation_fee' => $totalAmount,
                'policy_id' => null,
                'hours_until_start' => round($hoursUntilStart, 2),
            ];
        }

        $applicable = DB::table('cancellation_policies')
            ->where('is_active', true)
            ->where('hours_before', '<=', $hoursUntilStart)
            ->orderByDesc('hours_before')
            ->orderByDesc('refund_percentage')
            ->first();

        if (! $applicable) {
            return [
                'refund_amount' => 0,
                'refund_percentage' => 0,
                'cancellation_fee' => $totalAmount,
                'policy_id' => null,
                'hours_until_start' => round($hoursUntilStart, 2),
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
            'hours_until_start' => round($hoursUntilStart, 2),
        ];
    }
}
