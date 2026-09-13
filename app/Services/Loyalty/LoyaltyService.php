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
            $balance = $this->getBalance($userId);
            $rule = $this->getActiveRule(LoyaltyRuleType::Redemption);

            if (! $rule) {
                throw new BusinessException('Redemption is not configured.', 'redemption_not_configured');
            }

            if ($points < (int) $rule->min_redemption_points) {
                throw new BusinessException(
                    "Minimum redemption is {$rule->min_redemption_points} points.",
                    'min_redemption_not_met',
                );
            }

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

    public function calculateRedemptionValue(int $userId, int $points, float $bookingSubtotal): float
    {
        $balance = $this->getBalance($userId);
        $rule = $this->getActiveRule(LoyaltyRuleType::Redemption);

        if (! $rule || $points <= 0) {
            return 0;
        }

        if ($points > $balance) {
            throw new BusinessException('Insufficient points balance.', 'insufficient_points');
        }

        if ($points < (int) $rule->min_redemption_points) {
            throw new BusinessException(
                "Minimum redemption is {$rule->min_redemption_points} points.",
                'min_redemption_not_met',
            );
        }

        $conversionRate = (float) ($rule->value ?: 1);
        $monetaryValue = round($points / $conversionRate, 2);

        if ($rule->max_redemption_amount) {
            $monetaryValue = min($monetaryValue, (float) $rule->max_redemption_amount);
        }

        if ($rule->max_redemption_percentage) {
            $maxByPercentage = round($bookingSubtotal * ((float) $rule->max_redemption_percentage / 100), 2);
            $monetaryValue = min($monetaryValue, $maxByPercentage);
        }

        return min($monetaryValue, $bookingSubtotal);
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
