<?php

namespace App\Services\Studio;

use App\Enums\BookingHoldStatus;
use App\Exceptions\BusinessException;
use App\Models\BookingHold;
use App\Models\User;
use App\Repositories\Contracts\BookingHoldRepositoryInterface;
use App\Repositories\Contracts\StudioRepositoryInterface;
use App\Services\Content\AppSettingsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HoldService
{
    public function __construct(
        protected BookingHoldRepositoryInterface $bookingHoldRepository,
        protected StudioRepositoryInterface $studioRepository,
        protected AvailabilityService $availabilityService,
        protected AppSettingsService $appSettingsService,
    ) {
    }

    public function createHold(User $user, array $data): BookingHold
    {
        return DB::transaction(function () use ($user, $data) {
            $this->studioRepository->findOrFail($data['studio_id']);

            $startAt = Carbon::parse($data['start_at']);
            $endAt = Carbon::parse($data['end_at']);
            $durationMinutes = $startAt->diffInMinutes($endAt);

            $slots = $this->availabilityService->getAvailableSlots(
                $data['studio_id'],
                $startAt->toDateString(),
                $durationMinutes,
                $data['guest_count'] ?? null,
                $data['equipment_ids'] ?? null,
                $data['hospitality_ids'] ?? null,
            );

            $isAvailable = collect($slots)->contains(function (array $slot) use ($startAt, $endAt) {
                return Carbon::parse($slot['start_at'])->equalTo($startAt)
                    && Carbon::parse($slot['end_at'])->equalTo($endAt);
            });

            if (! $isAvailable) {
                throw new BusinessException('Selected slot is no longer available.', 'slot_unavailable');
            }

            $config = $this->appSettingsService->getBookingConfig();
            $expiresAt = now()->addMinutes($config['booking_hold_minutes']);

            return $this->bookingHoldRepository->create([
                'user_id' => $user->id,
                'studio_id' => $data['studio_id'],
                'start_at' => $startAt,
                'end_at' => $endAt,
                'expires_at' => $expiresAt,
                'status' => BookingHoldStatus::Active,
            ]);
        });
    }

    public function releaseHold(int $holdId, User $user): BookingHold
    {
        $hold = $this->bookingHoldRepository->findOrFail($holdId);

        if ($hold->user_id !== $user->id) {
            throw new BusinessException('Hold does not belong to this user.', 'hold_forbidden', 403);
        }

        if ($hold->status !== BookingHoldStatus::Active) {
            throw new BusinessException('Hold is not active.', 'hold_not_active');
        }

        return $this->bookingHoldRepository->update($holdId, [
            'status' => BookingHoldStatus::Cancelled,
        ]);
    }

    public function expireHolds(): int
    {
        return $this->bookingHoldRepository->expirePast();
    }

    public function convertHold(int $holdId): BookingHold
    {
        return DB::transaction(function () use ($holdId) {
            $hold = $this->bookingHoldRepository->findOrFail($holdId);

            if ($hold->status !== BookingHoldStatus::Active) {
                throw new BusinessException('Hold is not active.', 'hold_not_active');
            }

            if ($hold->expires_at->isPast()) {
                $this->bookingHoldRepository->update($holdId, [
                    'status' => BookingHoldStatus::Expired,
                ]);

                throw new BusinessException('Hold has expired.', 'hold_expired');
            }

            return $this->bookingHoldRepository->update($holdId, [
                'status' => BookingHoldStatus::Converted,
            ]);
        });
    }
}
