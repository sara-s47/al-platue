<?php

namespace App\Jobs;

use App\Enums\LoyaltyRuleType;
use App\Enums\PointsTransactionType;
use App\Services\Loyalty\LoyaltyService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireLoyaltyPointsJob implements ShouldQueue
{
    use Queueable;

    public function handle(LoyaltyService $loyaltyService): void
    {
        $rule = DB::table('loyalty_rules')
            ->where('rule_type', LoyaltyRuleType::Expiration->value)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $rule || ! $rule->value) {
            return;
        }

        $cutoff = Carbon::now()->subDays((int) $rule->value);
        $userIds = DB::table('points_transactions')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('SUM(points) > 0')
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $expirablePoints = $this->calculateExpirablePoints((int) $userId, $cutoff);

            if ($expirablePoints <= 0) {
                continue;
            }

            try {
                $loyaltyService->expirePoints(
                    (int) $userId,
                    $expirablePoints,
                    "Points expired after {$rule->value} days",
                );
            } catch (\Throwable $exception) {
                Log::warning('Failed to expire loyalty points', [
                    'user_id' => $userId,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    protected function calculateExpirablePoints(int $userId, Carbon $cutoff): float
    {
        $earnedBeforeCutoff = (float) DB::table('points_transactions')
            ->where('user_id', $userId)
            ->whereIn('type', [
                PointsTransactionType::Earn->value,
                PointsTransactionType::Welcome->value,
                PointsTransactionType::CampaignAward->value,
                PointsTransactionType::ManualAdd->value,
            ])
            ->where('created_at', '<', $cutoff)
            ->sum('points');

        $alreadyExpired = abs((float) DB::table('points_transactions')
            ->where('user_id', $userId)
            ->where('type', PointsTransactionType::Expiration->value)
            ->sum('points'));

        $balance = (float) DB::table('points_transactions')
            ->where('user_id', $userId)
            ->sum('points');

        return max(0, min($balance, $earnedBeforeCutoff - $alreadyExpired));
    }
}
