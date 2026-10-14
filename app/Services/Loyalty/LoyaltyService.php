<?php

namespace App\Services\Loyalty;

use App\Enums\BookingStatus;
use App\Enums\LoyaltyRuleType;
use App\Enums\PointsTransactionType;
use App\Exceptions\BusinessException;
use App\Repositories\Contracts\LoyaltyRuleRepositoryInterface;
use App\Repositories\Contracts\PointsTransactionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Content\AppSettingsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    public function __construct(
        protected PointsTransactionRepositoryInterface $pointsTransactionRepository,
        protected LoyaltyRuleRepositoryInterface $loyaltyRuleRepository,
        protected UserRepositoryInterface $userRepository,
        protected AppSettingsService $appSettings,
    ) {
    }

    public function getBalance(int $userId): float
    {
        return (float) DB::table('points_transactions')
            ->where('user_id', $userId)
            ->sum('points');
    }

    public function awardWelcomePoints(int $userId): ?Model
    {
        return DB::transaction(function () use ($userId) {
            $existing = DB::table('points_transactions')
                ->where('user_id', $userId)
                ->where('type', PointsTransactionType::Welcome->value)
                ->exists();

            if ($existing) {
                return null;
            }

            $rule = $this->getActiveRule(LoyaltyRuleType::Welcome);

            if (! $rule) {
                return null;
            }

            return $this->recordTransaction(
                $userId,
                (float) $rule->points,
                PointsTransactionType::Welcome,
                'Welcome bonus points',
            );
        });
    }

    public function earnFromBooking(Model $booking): ?Model
    {
        $status = $booking->status instanceof BookingStatus
            ? $booking->status
            : BookingStatus::tryFrom((string) $booking->status);

        if ($status !== BookingStatus::Completed) {
            throw new BusinessException('Points can only be earned from completed bookings.', 'booking_not_completed');
        }

        return DB::transaction(function () use ($booking) {
            $existing = DB::table('points_transactions')
                ->where('booking_id', $booking->id)
                ->where('type', PointsTransactionType::Earn->value)
                ->exists();

            if ($existing) {
                return null;
            }

            $rule = $this->getActiveRule(LoyaltyRuleType::Booking)
                ?? $this->getActiveRule(LoyaltyRuleType::Spending);

            if (! $rule) {
                return null;
            }

            $points = $this->calculateEarnPoints($rule, (float) $booking->total_amount);

            if ($points <= 0) {
                return null;
            }

            return $this->recordTransaction(
                (int) $booking->user_id,
                $points,
                PointsTransactionType::Earn,
                "Points earned from booking {$booking->booking_number}",
                $booking->id,
            );
        });
    }

    public function redeemPoints(int $userId, int $points, int $bookingId): Model
    {
        return DB::transaction(function () use ($userId, $points, $bookingId) {
            // Serialize redemptions per user so two concurrent bookings cannot spend the same balance.
            DB::table('users')->where('id', $userId)->lockForUpdate()->first();

            $balance = $this->getBalance($userId);
            $rule = $this->getActiveRule(LoyaltyRuleType::Redemption);

            if (! $rule) {
                throw new BusinessException('Points redemption is not available right now.', 'redemption_not_configured');
            }

            // The minimum is enforced on the requested amount in resolveRedemption(); the spent amount may be lower when the discount is capped.
            if ($balance < $points) {
                throw new BusinessException('Insufficient points balance.', 'insufficient_points');
            }

            return $this->recordTransaction(
                $userId,
                -abs($points),
                PointsTransactionType::Redemption,
                "Points redeemed for booking #{$bookingId}",
                $bookingId,
            );
        });
    }

    /**
     * What the customer can redeem on an amount (before tax), based on the active redemption rule and balance.
     *
     * @return array{
     *     enabled: bool,
     *     balance: int,
     *     points_per_currency_unit: float|null,
     *     min_redemption_points: int,
     *     max_redemption_amount: float|null,
     *     max_redemption_percentage: float|null,
     *     max_discount: float,
     *     max_redeemable_points: int,
     *     can_redeem: bool
     * }
     */
    public function getRedemptionOptions(int $userId, ?float $payableAmount = null): array
    {
        $balance = (int) floor($this->getBalance($userId));
        $rule = $this->getActiveRule(LoyaltyRuleType::Redemption);

        if (! $rule) {
            return [
                'enabled' => false,
                'balance' => $balance,
                'points_per_currency_unit' => null,
                'min_redemption_points' => 0,
                'max_redemption_amount' => null,
                'max_redemption_percentage' => null,
                'max_discount' => 0.0,
                'max_redeemable_points' => 0,
                'can_redeem' => false,
            ];
        }

        $rate = $this->conversionRate($rule);
        $minPoints = (int) $rule->min_redemption_points;
        $maxDiscount = $payableAmount === null ? null : $this->maxDiscountFor($rule, $payableAmount);
        $byBalance = round($balance / $rate, 2);
        $maxDiscount = $maxDiscount === null ? $byBalance : min($maxDiscount, $byBalance);
        $maxPoints = min($balance, (int) ceil(round($maxDiscount * $rate, 4)));

        return [
            'enabled' => true,
            'balance' => $balance,
            'points_per_currency_unit' => $rate,
            'min_redemption_points' => $minPoints,
            'max_redemption_amount' => $rule->max_redemption_amount !== null ? (float) $rule->max_redemption_amount : null,
            'max_redemption_percentage' => $rule->max_redemption_percentage !== null ? (float) $rule->max_redemption_percentage : null,
            'max_discount' => round($maxDiscount, 2),
            'max_redeemable_points' => $maxPoints,
            'can_redeem' => $balance >= max(1, $minPoints) && $maxPoints > 0,
        ];
    }

    /**
     * Resolve the points actually spent and the discount they give. Only the points needed for the
     * (possibly capped) discount are spent, so the customer never loses points that gave no discount.
     *
     * @return array{points_used: int, discount: float}
     */
    public function resolveRedemption(int $userId, ?int $requestedPoints, bool $useMaxPoints, float $payableAmount): array
    {
        if (! $useMaxPoints && ! $requestedPoints) {
            return ['points_used' => 0, 'discount' => 0.0];
        }

        $rule = $this->getActiveRule(LoyaltyRuleType::Redemption);

        if (! $rule) {
            throw new BusinessException('Points redemption is not available right now.', 'redemption_not_configured');
        }

        $balance = (int) floor($this->getBalance($userId));
        $minPoints = (int) $rule->min_redemption_points;

        if ($balance < max(1, $minPoints)) {
            throw new BusinessException(
                $minPoints > 0 ? "You need at least {$minPoints} points to redeem." : 'You have no points to redeem.',
                $minPoints > 0 ? 'min_redemption_not_met' : 'insufficient_points',
                422,
                null,
                ['balance' => $balance, 'min_redemption_points' => $minPoints],
            );
        }

        if ($useMaxPoints) {
            $requestedPoints = $balance;
        } else {
            if ($requestedPoints > $balance) {
                throw new BusinessException(
                    'Insufficient points balance.',
                    'insufficient_points',
                    422,
                    null,
                    ['balance' => $balance, 'requested' => $requestedPoints],
                );
            }

            if ($requestedPoints < $minPoints) {
                throw new BusinessException(
                    "Minimum redemption is {$minPoints} points.",
                    'min_redemption_not_met',
                    422,
                    null,
                    ['balance' => $balance, 'min_redemption_points' => $minPoints, 'requested' => $requestedPoints],
                );
            }
        }

        $rate = $this->conversionRate($rule);
        $discount = min(round($requestedPoints / $rate, 2), $this->maxDiscountFor($rule, $payableAmount));

        if ($discount <= 0) {
            return ['points_used' => 0, 'discount' => 0.0];
        }

        $pointsUsed = min($requestedPoints, (int) ceil(round($discount * $rate, 4)));

        return ['points_used' => $pointsUsed, 'discount' => $discount];
    }

    protected function conversionRate(object $rule): float
    {
        return (float) ($rule->value ?: 1);
    }

    protected function maxDiscountFor(object $rule, float $payableAmount): float
    {
        $max = max(0, $payableAmount);

        if ($rule->max_redemption_amount) {
            $max = min($max, (float) $rule->max_redemption_amount);
        }

        if ($rule->max_redemption_percentage) {
            $max = min($max, round($payableAmount * ((float) $rule->max_redemption_percentage / 100), 2));
        }

        return round($max, 2);
    }

    public function reversePoints(int $userId, int $bookingId, string $reason): ?Model
    {
        return DB::transaction(function () use ($userId, $bookingId, $reason) {
            $earned = DB::table('points_transactions')
                ->where('user_id', $userId)
                ->where('booking_id', $bookingId)
                ->where('type', PointsTransactionType::Earn->value)
                ->first();

            if ($earned) {
                $this->recordTransaction(
                    $userId,
                    -abs((float) $earned->points),
                    PointsTransactionType::Reversal,
                    $reason,
                    $bookingId,
                );
            }

            $redeemed = DB::table('points_transactions')
                ->where('user_id', $userId)
                ->where('booking_id', $bookingId)
                ->where('type', PointsTransactionType::Redemption->value)
                ->first();

            if ($redeemed) {
                return $this->recordTransaction(
                    $userId,
                    abs((float) $redeemed->points),
                    PointsTransactionType::Refund,
                    $reason,
                    $bookingId,
                );
            }

            return null;
        });
    }

    public function expirePoints(int $userId, float $points, string $reason = 'Points expired'): Model
    {
        return DB::transaction(function () use ($userId, $points, $reason) {
            $balance = $this->getBalance($userId);
            $toExpire = min($points, $balance);

            if ($toExpire <= 0) {
                throw new BusinessException('No points to expire.', 'no_points_to_expire');
            }

            return $this->recordTransaction(
                $userId,
                -abs($toExpire),
                PointsTransactionType::Expiration,
                $reason,
            );
        });
    }

    public function manualAdjust(int $userId, float $points, string $reason, int $adminUserId): Model
    {
        return DB::transaction(function () use ($userId, $points, $reason, $adminUserId) {
            $this->userRepository->findOrFail($userId);

            $type = $points >= 0
                ? PointsTransactionType::ManualAdd
                : PointsTransactionType::ManualDeduction;

            return $this->recordTransaction(
                $userId,
                $points,
                $type,
                $reason,
                null,
                $adminUserId,
            );
        });
    }

    protected function getActiveRule(LoyaltyRuleType $type): ?object
    {
        return DB::table('loyalty_rules')
            ->where('rule_type', $type->value)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }

    protected function calculateEarnPoints(object $rule, float $bookingTotal): float
    {
        if ($rule->rule_type === LoyaltyRuleType::Spending->value && $rule->value > 0) {
            return round($bookingTotal / (float) $rule->value * (float) $rule->points, 2);
        }

        return (float) $rule->points;
    }

    protected function recordTransaction(
        int $userId,
        float $points,
        PointsTransactionType $type,
        string $reason,
        ?int $bookingId = null,
        ?int $createdBy = null,
        ?int $campaignId = null,
    ): Model {
        return $this->pointsTransactionRepository->create([
            'user_id' => $userId,
            'booking_id' => $bookingId,
            'campaign_id' => $campaignId,
            'type' => $type->value,
            'points' => $points,
            'reason' => $reason,
            'created_by' => $createdBy,
            'created_at' => now(),
        ]);
    }
}
