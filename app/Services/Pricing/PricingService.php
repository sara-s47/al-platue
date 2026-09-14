<?php

namespace App\Services\Pricing;

use App\Enums\BookingMode;
use App\Enums\HospitalityPricingModel;
use App\Enums\PricingRuleType;
use App\Exceptions\BusinessException;
use App\Repositories\Contracts\EquipmentRepositoryInterface;
use App\Repositories\Contracts\HospitalityItemRepositoryInterface;
use App\Repositories\Contracts\PackageRepositoryInterface;
use App\Repositories\Contracts\PricingRuleRepositoryInterface;
use App\Services\Content\AppSettingsService;
use App\Services\Inventory\PackageService;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Promotion\PromoCodeService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PricingService
{
    private const RULE_PRECEDENCE = [
        PricingRuleType::DateSpecific->value => 4,
        PricingRuleType::Peak->value => 3,
        PricingRuleType::Weekend->value => 2,
        PricingRuleType::Base->value => 1,
    ];

    public function __construct(
        protected PricingRuleRepositoryInterface $pricingRuleRepository,
        protected EquipmentRepositoryInterface $equipmentRepository,
        protected HospitalityItemRepositoryInterface $hospitalityItemRepository,
        protected PackageRepositoryInterface $packageRepository,
        protected PackageService $packageService,
        protected PromoCodeService $promoCodeService,
        protected LoyaltyService $loyaltyService,
        protected AppSettingsService $appSettings,
    ) {
    }

    /**
     * @param  array<int, array{id: int, quantity: int}>  $equipment
     * @param  array<int, array{id: int, quantity: int}>  $hospitality
     * @param  list<array{date: string, open_time: string, close_time: string}>|null  $days
     * @return array<string, mixed>
     */
    public function calculateQuote(
        int $studioId,
        Carbon $startAt,
        Carbon $endAt,
        array $equipment = [],
        array $hospitality = [],
        ?int $packageId = null,
        ?string $promoCode = null,
        ?int $pointsToRedeem = null,
        ?int $userId = null,
        int $guestCount = 1,
        BookingMode $bookingMode = BookingMode::Hourly,
        ?array $days = null,
    ): array {
        if ($endAt->lte($startAt)) {
            throw new BusinessException('End time must be after start time.', 'invalid_time_range');
        }

        if ($bookingMode === BookingMode::Daily && $packageId !== null) {
            throw new BusinessException('Packages cannot be used with daily bookings.', 'package_not_allowed_for_daily');
        }

        $lineItems = [];
        $subtotal = 0.0;
        $dayBreakdown = [];

        if ($packageId !== null) {
            $this->packageService->validatePackageItems($packageId, $studioId);
            $package = $this->packageRepository->findOrFail($packageId);
            $packagePrice = (float) $package->price;

            $lineItems[] = [
                'item_type' => 'package',
                'item_id' => $packageId,
                'description' => $package->name,
                'quantity' => 1,
                'unit_price' => $packagePrice,
                'discount_amount' => 0,
                'total_price' => $packagePrice,
            ];

            $subtotal += $packagePrice;
        } elseif ($bookingMode === BookingMode::Daily) {
            if (empty($days)) {
                throw new BusinessException('Daily booking days are required for pricing.', 'daily_days_required');
            }

            $studioTotal = $this->calculateStudioDailyPrice($studioId, $days);
            $dayBreakdown = $studioTotal['days'];
            $daysCount = count($days);

            $lineItems[] = [
                'item_type' => 'studio',
                'item_id' => $studioId,
                'description' => "Studio daily rental ({$daysCount} day".($daysCount > 1 ? 's' : '').')',
                'quantity' => $daysCount,
                'unit_price' => $studioTotal['average_daily_rate'],
                'discount_amount' => 0,
                'total_price' => $studioTotal['total'],
            ];
            $subtotal += $studioTotal['total'];
        } else {
            $studioTotal = $this->calculateStudioPrice($studioId, $startAt, $endAt);
            $lineItems[] = [
                'item_type' => 'studio',
                'item_id' => $studioId,
                'description' => 'Studio rental',
                'quantity' => round($startAt->diffInMinutes($endAt) / 60, 2),
                'unit_price' => $studioTotal['effective_hourly_rate'],
                'discount_amount' => 0,
                'total_price' => $studioTotal['total'],
            ];
            $subtotal += $studioTotal['total'];
        }

        foreach ($equipment as $item) {
            $record = $this->equipmentRepository->findOrFail((int) $item['id']);
            $qty = (int) $item['quantity'];
            $unitPrice = (float) $record->price;
            $total = round($unitPrice * $qty, 2);

            $lineItems[] = [
                'item_type' => 'equipment',
                'item_id' => $record->id,
                'description' => $record->name,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'discount_amount' => 0,
                'total_price' => $total,
            ];
            $subtotal += $total;
        }

        foreach ($hospitality as $item) {
            $record = $this->hospitalityItemRepository->findOrFail((int) $item['id']);
            $qty = (int) $item['quantity'];
            $total = $this->calculateHospitalityPrice($record, $qty, $guestCount);

            $lineItems[] = [
                'item_type' => 'hospitality',
                'item_id' => $record->id,
                'description' => $record->name,
                'quantity' => $qty,
                'unit_price' => $qty > 0 ? round($total / $qty, 2) : 0,
                'discount_amount' => 0,
                'total_price' => $total,
            ];
            $subtotal += $total;
        }

        $discountAmount = 0.0;
        $promoDetails = null;

        if ($promoCode && $userId) {
            $promoResult = $this->promoCodeService->validate($promoCode, $userId, $studioId, $subtotal);
            $discountAmount = $promoResult['discount_amount'];
            $promoDetails = $promoResult;
        }

        $pointsDiscount = 0.0;

        if ($pointsToRedeem && $userId) {
            $pointsDiscount = $this->loyaltyService->calculateRedemptionValue($userId, $pointsToRedeem, $subtotal - $discountAmount);
        }

        $taxRate = (float) $this->appSettings->get('tax_rate', 0);
        $taxable = max(0, $subtotal - $discountAmount - $pointsDiscount);
        $taxAmount = round($taxable * ($taxRate / 100), 2);
        $totalAmount = round($taxable + $taxAmount, 2);

        return [
            'studio_id' => $studioId,
            'booking_mode' => $bookingMode->value,
            'days_count' => $bookingMode === BookingMode::Daily ? count($days ?? []) : null,
            'days' => $bookingMode === BookingMode::Daily ? $dayBreakdown : null,
            'start_at' => $startAt->toIso8601String(),
            'end_at' => $endAt->toIso8601String(),
            'line_items' => $lineItems,
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($discountAmount, 2),
            'points_discount' => round($pointsDiscount, 2),
            'points_to_redeem' => $pointsToRedeem,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'promo' => $promoDetails,
        ];
    }

    /**
     * @return array{total: float, effective_hourly_rate: float}
     */
    public function calculateStudioPrice(int $studioId, Carbon $startAt, Carbon $endAt): array
    {
        $rules = $this->getActiveRules($studioId);
        $intervalMinutes = (int) $this->appSettings->get('booking_interval_minutes', 60);
        $total = 0.0;
        $totalMinutes = 0;

        $cursor = $startAt->copy();

        while ($cursor->lt($endAt)) {
            $segmentEnd = $cursor->copy()->addMinutes($intervalMinutes);

            if ($segmentEnd->gt($endAt)) {
                $segmentEnd = $endAt->copy();
            }

            $segmentMinutes = $cursor->diffInMinutes($segmentEnd);
            $hourlyRate = $this->resolveHourlyRate($rules, $cursor);
            $total += ($segmentMinutes / 60) * $hourlyRate;
            $totalMinutes += $segmentMinutes;
            $cursor = $segmentEnd;
        }

        $effectiveRate = $totalMinutes > 0 ? round($total / ($totalMinutes / 60), 2) : 0;

        return [
            'total' => round($total, 2),
            'effective_hourly_rate' => $effectiveRate,
        ];
    }

    /**
     * @param  list<array{date: string, open_time: string, close_time: string}>  $days
     * @return array{total: float, average_daily_rate: float, days: list<array{date: string, open_time: string, close_time: string, daily_rate: float}>}
     */
    public function calculateStudioDailyPrice(int $studioId, array $days): array
    {
        $rules = $this->getActiveRules($studioId);
        $timezone = $this->appSettings->getBookingConfig()['timezone'];
        $total = 0.0;
        $breakdown = [];

        foreach ($days as $day) {
            $moment = Carbon::parse($day['date'].' '.$day['open_time'], $timezone);
            $dailyRate = $this->resolveDailyRate($rules, $moment);
            $total += $dailyRate;
            $breakdown[] = [
                'date' => $day['date'],
                'open_time' => $day['open_time'],
                'close_time' => $day['close_time'],
                'daily_rate' => $dailyRate,
            ];
        }

        $count = count($days);

        return [
            'total' => round($total, 2),
            'average_daily_rate' => $count > 0 ? round($total / $count, 2) : 0,
            'days' => $breakdown,
        ];
    }

    protected function getActiveRules(int $studioId): Collection
    {
        return DB::table('pricing_rules')
            ->where('studio_id', $studioId)
            ->where('is_active', true)
            ->get();
    }

    protected function resolveHourlyRate(Collection $rules, Carbon $moment): float
    {
        $best = $this->resolveBestRule($rules, $moment, forDaily: false);

        return (float) $best->price_per_hour;
    }

    protected function resolveDailyRate(Collection $rules, Carbon $moment): float
    {
        $best = $this->resolveBestRule($rules, $moment, forDaily: true);

        if ($best->price_per_day === null) {
            throw new BusinessException(
                'Daily price is not configured for the selected date.',
                'daily_price_not_configured',
            );
        }

        return (float) $best->price_per_day;
    }

    protected function resolveBestRule(Collection $rules, Carbon $moment, bool $forDaily): object
    {
        $matching = $rules->filter(function ($rule) use ($moment, $forDaily) {
            if ($forDaily && $rule->price_per_day === null) {
                return false;
            }

            if ($rule->rule_type === PricingRuleType::DateSpecific->value) {
                return $rule->specific_date === $moment->toDateString();
            }

            if ($rule->rule_type === PricingRuleType::Weekend->value) {
                $weekendDays = [5, 6];

                if ($rule->day_of_week !== null) {
                    return (int) $rule->day_of_week === (int) $moment->dayOfWeek;
                }

                return in_array((int) $moment->dayOfWeek, $weekendDays, true);
            }

            if ($rule->rule_type === PricingRuleType::Peak->value) {
                // Peak time windows apply to hourly only; skip for whole-day pricing.
                if ($forDaily) {
                    return false;
                }

                if ($rule->day_of_week !== null && (int) $rule->day_of_week !== (int) $moment->dayOfWeek) {
                    return false;
                }

                if ($rule->start_time && $rule->end_time) {
                    $time = $moment->format('H:i:s');

                    return $time >= $rule->start_time && $time < $rule->end_time;
                }

                return true;
            }

            return $rule->rule_type === PricingRuleType::Base->value;
        });

        if ($matching->isEmpty()) {
            throw new BusinessException(
                $forDaily
                    ? 'No daily pricing rule found for the requested date.'
                    : 'No pricing rule found for the requested time.',
                $forDaily ? 'daily_price_not_configured' : 'pricing_rule_not_found',
            );
        }

        return $matching->sortByDesc(function ($rule) {
            $precedence = self::RULE_PRECEDENCE[$rule->rule_type] ?? 0;

            return ($precedence * 1000) + (int) $rule->priority;
        })->first();
    }

    protected function calculateHospitalityPrice(object $item, int $quantity, int $guestCount): float
    {
        $price = (float) $item->price;

        return match ($item->pricing_model) {
            HospitalityPricingModel::Included->value => 0,
            HospitalityPricingModel::PerPerson->value => round($price * $guestCount * $quantity, 2),
            HospitalityPricingModel::PerPackage->value => round($price * $quantity, 2),
            default => round($price * $quantity, 2),
        };
    }
}
