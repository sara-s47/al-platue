<?php

namespace App\Services\Promotion;

use App\Enums\PromoCodeType;
use App\Exceptions\BusinessException;
use App\Repositories\Contracts\PromoCodeRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PromoCodeService
{
    public function __construct(
        protected PromoCodeRepositoryInterface $promoCodeRepository,
    ) {
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->promoCodeRepository->paginate($perPage);
    }

    public function findOrFail(int $id): Model
    {
        return $this->promoCodeRepository->findOrFail($id);
    }

    public function create(array $data, array $studioIds = []): Model
    {
        return DB::transaction(function () use ($data, $studioIds) {
            $promo = $this->promoCodeRepository->create($data);
            $this->syncStudios($promo->id, $studioIds);

            return $promo->fresh();
        });
    }

    public function update(int $id, array $data, ?array $studioIds = null): Model
    {
        return DB::transaction(function () use ($id, $data, $studioIds) {
            $promo = $this->promoCodeRepository->update($id, $data);

            if ($studioIds !== null) {
                $this->syncStudios($promo->id, $studioIds);
            }

            return $promo->fresh();
        });
    }

    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            DB::table('promo_code_studios')->where('promo_code_id', $id)->delete();

            return $this->promoCodeRepository->delete($id);
        });
    }

    /**
     * @return array{valid: bool, promo_code_id: int, discount_amount: float, code: string}
     */
    public function validate(
        string $code,
        int $userId,
        int $studioId,
        float $bookingSubtotal,
    ): array {
        $promo = DB::table('promo_codes')->where('code', strtoupper(trim($code)))->first();

        if (! $promo) {
            throw new BusinessException('Invalid promo code.', 'promo_invalid');
        }

        $this->assertPromoEligible($promo, $userId, $studioId, $bookingSubtotal);

        $discountAmount = $this->calculateDiscount($promo, $bookingSubtotal);

        return [
            'valid' => true,
            'promo_code_id' => (int) $promo->id,
            'discount_amount' => $discountAmount,
            'code' => $promo->code,
        ];
    }

    public function apply(int $promoCodeId, int $userId, int $bookingId, float $discountAmount): void
    {
        DB::table('promo_code_usages')->insert([
            'promo_code_id' => $promoCodeId,
            'user_id' => $userId,
            'booking_id' => $bookingId,
            'discount_amount' => $discountAmount,
            'used_at' => now(),
        ]);
    }

    protected function assertPromoEligible(object $promo, int $userId, int $studioId, float $bookingSubtotal): void
    {
        if (! $promo->is_active) {
            throw new BusinessException('Promo code is not active.', 'promo_inactive');
        }

        $now = Carbon::now();

        if ($now->lt(Carbon::parse($promo->valid_from))) {
            throw new BusinessException('Promo code is not yet valid.', 'promo_not_started');
        }

        if ($now->gt(Carbon::parse($promo->valid_to))) {
            throw new BusinessException('Promo code has expired.', 'promo_expired');
        }

        if ($bookingSubtotal < (float) $promo->minimum_booking_value) {
            throw new BusinessException('Booking does not meet minimum value for this promo.', 'promo_min_value');
        }

        $studioRestricted = DB::table('promo_code_studios')->where('promo_code_id', $promo->id)->exists();

        if ($studioRestricted) {
            $allowed = DB::table('promo_code_studios')
                ->where('promo_code_id', $promo->id)
                ->where('studio_id', $studioId)
                ->exists();

            if (! $allowed) {
                throw new BusinessException('Promo code is not valid for this studio.', 'promo_studio_restricted');
            }
        }

        if ($promo->usage_limit !== null) {
            $totalUsage = DB::table('promo_code_usages')->where('promo_code_id', $promo->id)->count();

            if ($totalUsage >= (int) $promo->usage_limit) {
                throw new BusinessException('Promo code usage limit reached.', 'promo_usage_limit');
            }
        }

        if ($promo->per_user_limit !== null) {
            $userUsage = DB::table('promo_code_usages')
                ->where('promo_code_id', $promo->id)
                ->where('user_id', $userId)
                ->count();

            if ($userUsage >= (int) $promo->per_user_limit) {
                throw new BusinessException('You have reached the usage limit for this promo code.', 'promo_user_limit');
            }
        }
    }

    protected function calculateDiscount(object $promo, float $subtotal): float
    {
        if ($promo->type === PromoCodeType::Percentage->value) {
            return round($subtotal * ((float) $promo->value / 100), 2);
        }

        return round(min((float) $promo->value, $subtotal), 2);
    }

    protected function syncStudios(int $promoCodeId, array $studioIds): void
    {
        DB::table('promo_code_studios')->where('promo_code_id', $promoCodeId)->delete();

        foreach ($studioIds as $studioId) {
            DB::table('promo_code_studios')->insert([
                'promo_code_id' => $promoCodeId,
                'studio_id' => $studioId,
            ]);
        }
    }
}
