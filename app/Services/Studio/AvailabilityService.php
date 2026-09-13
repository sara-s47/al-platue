<?php

namespace App\Services\Studio;

use App\Enums\EquipmentStatus;
use App\Exceptions\BusinessException;
use App\Repositories\Contracts\BookingHoldRepositoryInterface;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\StudioBlockRepositoryInterface;
use App\Repositories\Contracts\StudioRepositoryInterface;
use App\Services\Content\AppSettingsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AvailabilityService
{
    public function __construct(
        protected StudioRepositoryInterface $studioRepository,
        protected ScheduleService $scheduleService,
        protected BookingRepositoryInterface $bookingRepository,
        protected BookingHoldRepositoryInterface $bookingHoldRepository,
        protected StudioBlockRepositoryInterface $studioBlockRepository,
        protected AppSettingsService $appSettingsService,
    ) {
    }

    public function getAvailableSlots(
        int $studioId,
        string $date,
        int $durationMinutes,
        ?int $guestCount = null,
        ?array $equipmentIds = null,
        ?array $hospitalityIds = null,
    ): array {
        $studio = $this->studioRepository->findOrFail($studioId);

        if (! $studio->is_active) {
            return [];
        }

        if ($guestCount !== null && $guestCount > $studio->capacity) {
            throw new BusinessException('Guest count exceeds studio capacity.', 'capacity_exceeded');
        }

        $config = $this->appSettingsService->getBookingConfig();
        $timezone = $config['timezone'];
        $interval = $config['booking_interval_minutes'];
        $minDuration = $config['minimum_booking_minutes'];
        $maxDuration = $config['maximum_booking_minutes'];
        $buffer = $config['cleanup_buffer_minutes'];

        if ($durationMinutes < $minDuration || $durationMinutes > $maxDuration) {
            throw new BusinessException('Requested duration is outside allowed booking limits.', 'invalid_duration');
        }

        if ($durationMinutes % $interval !== 0) {
            throw new BusinessException('Requested duration must align with booking interval.', 'invalid_duration_interval');
        }

        $schedule = $this->scheduleService->getEffectiveSchedule($studioId, $date);

        if ($schedule['is_closed'] || ! $schedule['open_time'] || ! $schedule['close_time']) {
            return [];
        }

        $dayStart = Carbon::parse($date.' '.$schedule['open_time'], $timezone);
        $dayEnd = Carbon::parse($date.' '.$schedule['close_time'], $timezone);

        $bookings = $this->bookingRepository->getOverlappingForStudio(
            $studioId,
            $dayStart->copy()->startOfDay()->toDateTimeString(),
            $dayEnd->copy()->endOfDay()->toDateTimeString(),
        );

        $holds = $this->bookingHoldRepository->getActiveOverlappingForStudio(
            $studioId,
            $dayStart->copy()->startOfDay()->toDateTimeString(),
            $dayEnd->copy()->endOfDay()->toDateTimeString(),
        );

        $blocks = $this->studioBlockRepository->getOverlappingForStudio(
            $studioId,
            $dayStart->copy()->startOfDay()->toDateTimeString(),
            $dayEnd->copy()->endOfDay()->toDateTimeString(),
        );

        $occupied = $this->buildOccupiedPeriods($bookings, $holds, $blocks, $buffer, $timezone);

        $slots = [];
        $cursor = $dayStart->copy();

        while ($cursor->copy()->addMinutes($durationMinutes)->lte($dayEnd)) {
            $slotStart = $cursor->copy();
            $slotEnd = $cursor->copy()->addMinutes($durationMinutes);

            if ($this->isSlotFree($slotStart, $slotEnd, $occupied, $buffer)
                && $this->hasEquipmentAvailability($studioId, $slotStart, $slotEnd, $equipmentIds)
                && $this->hasHospitalityAvailability($studioId, $slotStart, $slotEnd, $hospitalityIds)) {
                $slots[] = [
                    'start_at' => $slotStart->toDateTimeString(),
                    'end_at' => $slotEnd->toDateTimeString(),
                ];
            }

            $cursor->addMinutes($interval);
        }

        return $slots;
    }

    /**
     * Validate that a concrete time range can be booked for the studio.
     *
     * @throws BusinessException
     */
    public function validateSlot(
        int $studioId,
        Carbon $startAt,
        Carbon $endAt,
        int $guestCount,
        ?int $excludeBookingId = null,
        bool $enforceDurationLimits = true,
    ): void {
        $studio = $this->studioRepository->findOrFail($studioId);

        if (! $studio->is_active) {
            throw new BusinessException('Studio is not available for booking.', 'studio_inactive');
        }

        if ($endAt->lte($startAt)) {
            throw new BusinessException('End time must be after start time.', 'invalid_time_range');
        }

        if ($guestCount < 1) {
            throw new BusinessException('Guest count must be at least 1.', 'invalid_guest_count');
        }

        if ($guestCount > (int) $studio->capacity) {
            throw new BusinessException('Guest count exceeds studio capacity.', 'capacity_exceeded');
        }

        $config = $this->appSettingsService->getBookingConfig();
        $timezone = $config['timezone'];
        $interval = $config['booking_interval_minutes'];
        $minDuration = $config['minimum_booking_minutes'];
        $maxDuration = $config['maximum_booking_minutes'];
        $buffer = $config['cleanup_buffer_minutes'];

        $startAt = $startAt->copy()->timezone($timezone);
        $endAt = $endAt->copy()->timezone($timezone);
        $durationMinutes = (int) $startAt->diffInMinutes($endAt);

        if ($enforceDurationLimits) {
            if ($durationMinutes < $minDuration || $durationMinutes > $maxDuration) {
                throw new BusinessException('Requested duration is outside allowed booking limits.', 'invalid_duration');
            }

            if ($durationMinutes % $interval !== 0) {
                throw new BusinessException('Requested duration must align with booking interval.', 'invalid_duration_interval');
            }
        }

        $date = $startAt->toDateString();

        if ($endAt->toDateString() !== $date) {
            throw new BusinessException('Bookings must start and end on the same day.', 'invalid_time_range');
        }

        $schedule = $this->scheduleService->getEffectiveSchedule($studioId, $date);

        if ($schedule['is_closed'] || ! $schedule['open_time'] || ! $schedule['close_time']) {
            throw new BusinessException('Studio is closed on the selected date.', 'studio_closed');
        }

        $dayStart = Carbon::parse($date.' '.$schedule['open_time'], $timezone);
        $dayEnd = Carbon::parse($date.' '.$schedule['close_time'], $timezone);

        if ($startAt->lt($dayStart) || $endAt->gt($dayEnd)) {
            throw new BusinessException('Selected slot is outside studio opening hours.', 'outside_opening_hours');
        }

        $bookings = $this->bookingRepository->getOverlappingForStudio(
            $studioId,
            $startAt->toDateTimeString(),
            $endAt->toDateTimeString(),
        );

        if ($excludeBookingId) {
            $bookings = $bookings->reject(fn ($booking) => (int) $booking->id === $excludeBookingId)->values();
        }

        $holds = $this->bookingHoldRepository->getActiveOverlappingForStudio(
            $studioId,
            $startAt->toDateTimeString(),
            $endAt->toDateTimeString(),
        );

        $blocks = $this->studioBlockRepository->getOverlappingForStudio(
            $studioId,
            $startAt->toDateTimeString(),
            $endAt->toDateTimeString(),
        );

        $occupied = $this->buildOccupiedPeriods($bookings, $holds, $blocks, $buffer, $timezone);

        if (! $this->isSlotFree($startAt, $endAt, $occupied, $buffer)) {
            throw new BusinessException('Selected slot is no longer available.', 'slot_unavailable');
        }
    }

    protected function buildOccupiedPeriods($bookings, $holds, $blocks, int $buffer, string $timezone): array
    {
        $periods = [];

        foreach ($bookings as $booking) {
            $periods[] = [
                'start' => Carbon::parse($booking->start_at, $timezone)->subMinutes($buffer),
                'end' => Carbon::parse($booking->end_at, $timezone)->addMinutes($buffer),
            ];
        }

        foreach ($holds as $hold) {
            $periods[] = [
                'start' => Carbon::parse($hold->start_at, $timezone),
                'end' => Carbon::parse($hold->end_at, $timezone),
            ];
        }

        foreach ($blocks as $block) {
            $periods[] = [
                'start' => Carbon::parse($block->start_at, $timezone),
                'end' => Carbon::parse($block->end_at, $timezone),
            ];
        }

        return $periods;
    }

    protected function isSlotFree(Carbon $start, Carbon $end, array $occupied, int $buffer): bool
    {
        foreach ($occupied as $period) {
            if ($start->lt($period['end']) && $end->gt($period['start'])) {
                return false;
            }
        }

        return true;
    }

    protected function hasEquipmentAvailability(int $studioId, Carbon $start, Carbon $end, ?array $equipmentIds): bool
    {
        if (empty($equipmentIds)) {
            return true;
        }

        foreach ($equipmentIds as $equipmentId => $requestedQuantity) {
            if (is_int($equipmentId)) {
                $equipmentId = $requestedQuantity;
                $requestedQuantity = 1;
            }

            $available = $this->getAvailableEquipmentQuantity($studioId, (int) $equipmentId, $start, $end);

            if ($available < (int) $requestedQuantity) {
                return false;
            }
        }

        return true;
    }

    protected function hasHospitalityAvailability(int $studioId, Carbon $start, Carbon $end, ?array $hospitalityIds): bool
    {
        if (empty($hospitalityIds)) {
            return true;
        }

        foreach ($hospitalityIds as $hospitalityId => $requestedQuantity) {
            if (is_int($hospitalityId)) {
                $hospitalityId = $requestedQuantity;
                $requestedQuantity = 1;
            }

            $available = $this->getAvailableHospitalityQuantity($studioId, (int) $hospitalityId, $start, $end);

            if ($available < (int) $requestedQuantity) {
                return false;
            }
        }

        return true;
    }

    protected function getAvailableEquipmentQuantity(int $studioId, int $equipmentId, Carbon $start, Carbon $end): int
    {
        $studioEquipment = DB::table('studio_equipment')
            ->join('equipment', 'equipment.id', '=', 'studio_equipment.equipment_id')
            ->where('studio_equipment.studio_id', $studioId)
            ->where('studio_equipment.equipment_id', $equipmentId)
            ->where('equipment.status', EquipmentStatus::Active->value)
            ->select('studio_equipment.quantity as studio_quantity', 'equipment.quantity as total_quantity')
            ->first();

        if (! $studioEquipment) {
            return 0;
        }

        $totalAvailable = min((int) $studioEquipment->studio_quantity, (int) $studioEquipment->total_quantity);

        $allocated = (int) DB::table('booking_equipment')
            ->join('bookings', 'bookings.id', '=', 'booking_equipment.booking_id')
            ->where('bookings.studio_id', $studioId)
            ->where('booking_equipment.equipment_id', $equipmentId)
            ->whereIn('bookings.status', ['pending', 'confirmed', 'in_progress', 'extended'])
            ->where('bookings.start_at', '<', $end)
            ->where('bookings.end_at', '>', $start)
            ->sum('booking_equipment.quantity');

        return max(0, $totalAvailable - $allocated);
    }

    protected function getAvailableHospitalityQuantity(int $studioId, int $hospitalityItemId, Carbon $start, Carbon $end): int
    {
        $item = DB::table('studio_hospitality')
            ->join('hospitality_items', 'hospitality_items.id', '=', 'studio_hospitality.hospitality_item_id')
            ->where('studio_hospitality.studio_id', $studioId)
            ->where('studio_hospitality.hospitality_item_id', $hospitalityItemId)
            ->where('hospitality_items.is_active', true)
            ->select('hospitality_items.quantity')
            ->first();

        if (! $item) {
            return 0;
        }

        $totalAvailable = (int) $item->quantity;

        $allocated = (int) DB::table('booking_hospitality')
            ->join('bookings', 'bookings.id', '=', 'booking_hospitality.booking_id')
            ->where('bookings.studio_id', $studioId)
            ->where('booking_hospitality.hospitality_item_id', $hospitalityItemId)
            ->whereIn('bookings.status', ['pending', 'confirmed', 'in_progress', 'extended'])
            ->where('bookings.start_at', '<', $end)
            ->where('bookings.end_at', '>', $start)
            ->sum('booking_hospitality.quantity');

        return max(0, $totalAvailable - $allocated);
    }
}
