<?php

namespace App\Services\Reporting;

use App\Enums\BookingStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignUserStatus;
use App\Enums\PaymentStatus;
use App\Enums\PointsTransactionType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportingService
{
    /**
     * @return array<string, mixed>
     */
    public function revenue(?Carbon $from = null, ?Carbon $to = null, ?int $studioId = null): array
    {
        $query = DB::table('payments')
            ->join('bookings', 'bookings.id', '=', 'payments.booking_id')
            ->where('payments.status', PaymentStatus::Paid->value);

        $this->applyDateRange($query, 'payments.paid_at', $from, $to);

        if ($studioId) {
            $query->where('bookings.studio_id', $studioId);
        }

        $totals = (clone $query)
            ->selectRaw('COUNT(payments.id) as payment_count, COALESCE(SUM(payments.amount), 0) as total_revenue')
            ->first();

        $byType = (clone $query)
            ->selectRaw('payments.type, COUNT(*) as count, COALESCE(SUM(payments.amount), 0) as total')
            ->groupBy('payments.type')
            ->get();

        return [
            'payment_count' => (int) ($totals->payment_count ?? 0),
            'total_revenue' => (float) ($totals->total_revenue ?? 0),
            'by_type' => $byType,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function bookingVolume(?Carbon $from = null, ?Carbon $to = null, ?int $studioId = null): array
    {
        $query = DB::table('bookings');

        $this->applyDateRange($query, 'bookings.created_at', $from, $to);

        if ($studioId) {
            $query->where('studio_id', $studioId);
        }

        $totals = (clone $query)
            ->selectRaw('COUNT(*) as total_bookings, COALESCE(AVG(total_amount), 0) as average_booking_value')
            ->first();

        $byStatus = (clone $query)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        return [
            'total_bookings' => (int) ($totals->total_bookings ?? 0),
            'average_booking_value' => round((float) ($totals->average_booking_value ?? 0), 2),
            'by_status' => $byStatus,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function studioUtilization(?Carbon $from = null, ?Carbon $to = null, ?int $studioId = null): array
    {
        $from = $from ?? now()->startOfMonth();
        $to = $to ?? now()->endOfMonth();

        $studiosQuery = DB::table('studios')->where('is_active', true);

        if ($studioId) {
            $studiosQuery->where('id', $studioId);
        }

        $studios = $studiosQuery->get(['id', 'name']);
        $results = [];

        foreach ($studios as $studio) {
            $bookings = DB::table('bookings')
                ->where('studio_id', $studio->id)
                ->whereIn('status', [
                    BookingStatus::Confirmed->value,
                    BookingStatus::InProgress->value,
                    BookingStatus::Completed->value,
                    BookingStatus::Extended->value,
                ])
                ->where('start_at', '<', $to)
                ->where('end_at', '>', $from)
                ->get(['start_at', 'end_at']);

            $bookedMinutes = 0;

            foreach ($bookings as $booking) {
                $start = Carbon::parse($booking->start_at)->max($from);
                $end = Carbon::parse($booking->end_at)->min($to);
                $bookedMinutes += $start->diffInMinutes($end);
            }

            $scheduleDays = DB::table('studio_schedules')
                ->where('studio_id', $studio->id)
                ->where('is_closed', false)
                ->count();

            $availableMinutes = max($scheduleDays * 8 * 60, 1);
            $utilization = round(($bookedMinutes / $availableMinutes) * 100, 2);

            $results[] = [
                'studio_id' => $studio->id,
                'studio_name' => $studio->name,
                'booked_minutes' => $bookedMinutes,
                'available_minutes' => $availableMinutes,
                'utilization_percentage' => min($utilization, 100),
            ];
        }

        return [
            'from' => $from->toDateTimeString(),
            'to' => $to->toDateTimeString(),
            'studios' => $results,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function loyaltyMetrics(?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = DB::table('points_transactions');

        $this->applyDateRange($query, 'points_transactions.created_at', $from, $to);

        $earned = (clone $query)
            ->whereIn('type', [
                PointsTransactionType::Earn->value,
                PointsTransactionType::Welcome->value,
                PointsTransactionType::CampaignAward->value,
                PointsTransactionType::ManualAdd->value,
            ])
            ->sum('points');

        $redeemed = abs((float) (clone $query)
            ->where('type', PointsTransactionType::Redemption->value)
            ->sum('points'));

        $expired = abs((float) (clone $query)
            ->where('type', PointsTransactionType::Expiration->value)
            ->sum('points'));

        $activeUsers = DB::table('points_transactions')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('SUM(points) > 0')
            ->get()
            ->count();

        return [
            'points_earned' => (float) $earned,
            'points_redeemed' => $redeemed,
            'points_expired' => $expired,
            'active_users_with_points' => $activeUsers,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function campaignPerformance(?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = DB::table('loyalty_campaigns');

        $this->applyDateRange($query, 'loyalty_campaigns.created_at', $from, $to);

        $campaigns = (clone $query)
            ->select('id', 'name', 'points', 'status', 'created_at', 'expires_at')
            ->orderByDesc('created_at')
            ->get();

        $results = [];

        foreach ($campaigns as $campaign) {
            $userStats = DB::table('loyalty_campaign_users')
                ->where('campaign_id', $campaign->id)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status');

            $pointsAwarded = DB::table('points_transactions')
                ->where('campaign_id', $campaign->id)
                ->where('type', PointsTransactionType::CampaignAward->value)
                ->sum('points');

            $results[] = [
                'campaign_id' => $campaign->id,
                'name' => $campaign->name,
                'status' => $campaign->status,
                'target_points' => (float) $campaign->points,
                'points_awarded' => (float) $pointsAwarded,
                'users_pending' => (int) ($userStats[CampaignUserStatus::Pending->value] ?? 0),
                'users_awarded' => (int) ($userStats[CampaignUserStatus::Awarded->value] ?? 0),
                'users_failed' => (int) ($userStats[CampaignUserStatus::Failed->value] ?? 0),
                'users_excluded' => (int) ($userStats[CampaignUserStatus::Excluded->value] ?? 0),
                'is_completed' => $campaign->status === CampaignStatus::Completed->value,
            ];
        }

        return [
            'campaigns' => $results,
            'total_campaigns' => count($results),
        ];
    }

    protected function applyDateRange(\Illuminate\Database\Query\Builder $query, string $column, ?Carbon $from, ?Carbon $to): void
    {
        if ($from) {
            $query->where($column, '>=', $from);
        }

        if ($to) {
            $query->where($column, '<=', $to);
        }
    }
}
