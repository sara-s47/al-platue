<?php

namespace App\Services\Studio;

use App\Enums\EquipmentStatus;
use App\Exceptions\BusinessException;
use App\Repositories\Contracts\BookingHoldRepositoryInterface;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\StudioBlockRepositoryInterface;
use App\Repositories\Contracts\StudioRepositoryInterface;
use App\Services\Content\AppSettingsService;
use App\Services\Inventory\InventoryService;
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
        protected InventoryService $inventoryService,
    ) {
    }

    /**
     * @return array{
     *     slots: list<array{start_at: string, end_at: string}>,
     *     meta: array<string, mixed>
     * }
     */
    public function getAvailableSlots(
        int $studioId,
        string $date,
        int $durationMinutes,
        ?int $guestCount = null,
        ?array $equipmentIds = null,
        ?array $hospitalityIds = null,
        bool $enforceDurationLimits = true,
    ): array {
        $studio = $this->studioRepository->findOrFail($studioId);

        if (! $studio->is_active) {
            return $this->emptySlotsResult('studio_inactive');
        }

        if ($guestCount !== null && $guestCount > $studio->capacity) {
            throw new BusinessException('Guest count exceeds studio capacity.', 'capacity_exceeded');
        }

        $config = $this->appSettingsService->getBookingConfig();
        $timezone = $config['timezone'];
        $interval = max(1, (int) $config['booking_interval_minutes']);
        $minDuration = $config['minimum_booking_minutes'];
        $maxDuration = $config['maximum_booking_minutes'];
        $buffer = $config['cleanup_buffer_minutes'];

        if ($enforceDurationLimits) {
            if ($durationMinutes < $minDuration || $durationMinutes > $maxDuration) {
                throw new BusinessException('Requested duration is outside allowed booking limits.', 'invalid_duration');
            }

            if ($durationMinutes % $interval !== 0) {
                throw new BusinessException('Requested duration must align with booking interval.', 'invalid_duration_interval');
            }
        } elseif ($durationMinutes < 1) {
            throw new BusinessException('Requested duration must be positive.', 'invalid_duration');
        }

        $schedule = $this->scheduleService->getEffectiveSchedule($studioId, $date);

        if ($schedule['is_closed'] || ! $schedule['open_time'] || ! $schedule['close_time']) {
            return $this->emptySlotsResult('studio_closed', [
                'schedule' => [
                    'source' => $schedule['source'] ?? null,
                    'is_closed' => (bool) ($schedule['is_closed'] ?? true),
                    'open_time' => $schedule['open_time'] ?? null,
                    'close_time' => $schedule['close_time'] ?? null,
                    'override_id' => $schedule['override_id'] ?? null,
                ],
            ]);
        }

        $dayStart = Carbon::parse($date.' '.$schedule['open_time'], $timezone);
        $dayEnd = Carbon::parse($date.' '.$schedule['close_time'], $timezone);

        if ($dayEnd->lte($dayStart)) {
            return $this->emptySlotsResult('invalid_opening_hours', [
                'schedule' => [
                    'source' => $schedule['source'] ?? null,
                    'open_time' => $schedule['open_time'],
                    'close_time' => $schedule['close_time'],
                ],
            ]);
        }

        if ($dayStart->copy()->addMinutes($durationMinutes)->gt($dayEnd)) {
            return $this->emptySlotsResult('duration_exceeds_opening_hours', [
                'schedule' => [
                    'source' => $schedule['source'] ?? null,
                    'open_time' => $schedule['open_time'],
                    'close_time' => $schedule['close_time'],
                ],
                'duration_minutes' => $durationMinutes,
                'open_window_minutes' => (int) $dayStart->diffInMinutes($dayEnd),
            ]);
        }

        $rangeStart = $dayStart->copy()->startOfDay()->toDateTimeString();
        $rangeEnd = $dayEnd->copy()->endOfDay()->toDateTimeString();

        $bookings = $this->bookingRepository->getOverlappingForStudio($studioId, $rangeStart, $rangeEnd);
        $holds = $this->bookingHoldRepository->getActiveOverlappingForStudio($studioId, $rangeStart, $rangeEnd);
        $blocks = $this->studioBlockRepository->getOverlappingForStudio($studioId, $rangeStart, $rangeEnd);

        $occupied = $this->buildOccupiedPeriods($bookings, $holds, $blocks, $buffer, $timezone);

        $slots = [];
        $candidates = 0;
        $cursor = $dayStart->copy();

        while ($cursor->copy()->addMinutes($durationMinutes)->lte($dayEnd)) {
            $candidates++;
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

        $meta = [
            'date' => $date,
            'timezone' => $timezone,
            'duration_minutes' => $durationMinutes,
            'interval_minutes' => $interval,
            'schedule' => [
                'source' => $schedule['source'] ?? null,
                'open_time' => $schedule['open_time'],
                'close_time' => $schedule['close_time'],
                'is_closed' => false,
            ],
            'occupied' => [
                'bookings' => $bookings->count(),
                'holds' => $holds->count(),
                'blocks' => $blocks->count(),
            ],
            'candidates' => $candidates,
            'empty_reason' => null,
        ];

        if ($slots === []) {
            $meta['empty_reason'] = $candidates === 0
                ? 'no_slot_candidates'
                : 'fully_occupied';
        }

        return ['slots' => $slots, 'meta' => $meta];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array{slots: list<array{start_at: string, end_at: string}>, meta: array<string, mixed>}
     */
    protected function emptySlotsResult(string $reason, array $extra = []): array
    {
        return [
            'slots' => [],
            'meta' => array_merge(['empty_reason' => $reason], $extra),
        ];
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
        ?int $excludeHoldId = null,
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

        // Request times are studio wall-clock times (same as slots and stored values), so keep the clock and only attach the zone.
        $startAt = $startAt->copy()->shiftTimezone($timezone);
        $endAt = $endAt->copy()->shiftTimezone($timezone);
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

        $this->bookingHoldRepository->expirePast();

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

        if ($excludeHoldId) {
            $holds = $holds->reject(fn ($hold) => (int) $hold->id === $excludeHoldId)->values();
        }

        $blocks = $this->studioBlockRepository->getOverlappingForStudio(
            $studioId,
            $startAt->toDateTimeString(),
            $endAt->toDateTimeString(),
        );

        $conflict = $this->findConflict($startAt, $endAt, $bookings, $holds, $blocks, $buffer, $timezone);

        if ($conflict !== null) {
            throw new BusinessException(
                'Selected slot is no longer available.',
                $conflict['code'],
                422,
                null,
                $conflict['context'],
            );
        }
    }

    /**
     * Validate a multi-day booking range (open hours each day, no overnight stay for the customer).
     * Locks the studio from first open to last close so other bookings cannot overlap those days.
     *
     * @return array{
     *     start_at: Carbon,
     *     end_at: Carbon,
     *     days_count: int,
     *     days: list<array{date: string, open_time: string, close_time: string}>
     * }
     */
    public function validateDailyRange(
        int $studioId,
        string $startDate,
        string $endDate,
        int $guestCount,
        ?int $excludeBookingId = null,
        ?int $excludeHoldId = null,
    ): array {
        $studio = $this->studioRepository->findOrFail($studioId);

        if (! $studio->is_active) {
            throw new BusinessException('Studio is not available for booking.', 'studio_inactive');
        }

        if ($guestCount < 1) {
            throw new BusinessException('Guest count must be at least 1.', 'invalid_guest_count');
        }

        if ($guestCount > (int) $studio->capacity) {
            throw new BusinessException('Guest count exceeds studio capacity.', 'capacity_exceeded');
        }

        $config = $this->appSettingsService->getBookingConfig();
        $timezone = $config['timezone'];
        $maxDays = $config['max_daily_booking_days'];
        // buffer intentionally unused for daily range locking (see conflict check below)

        $start = Carbon::parse($startDate, $timezone)->startOfDay();
        $end = Carbon::parse($endDate, $timezone)->startOfDay();

        if ($end->lt($start)) {
            throw new BusinessException('End date must be on or after start date.', 'invalid_date_range');
        }

        $daysCount = (int) $start->diffInDays($end) + 1;

        if ($daysCount < 1 || $daysCount > $maxDays) {
            throw new BusinessException(
                "Daily bookings must be between 1 and {$maxDays} days.",
                'invalid_daily_duration',
            );
        }

        $days = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $date = $cursor->toDateString();
            $schedule = $this->scheduleService->getEffectiveSchedule($studioId, $date);

            if ($schedule['is_closed'] || ! $schedule['open_time'] || ! $schedule['close_time']) {
                throw new BusinessException(
                    "Studio is closed on {$date}.",
                    'studio_closed',
                );
            }

            $days[] = [
                'date' => $date,
                'open_time' => $schedule['open_time'],
                'close_time' => $schedule['close_time'],
            ];

            $cursor->addDay();
        }

        $rangeStart = Carbon::parse($days[0]['date'].' '.$days[0]['open_time'], $timezone);
        $lastDay = $days[array_key_last($days)];
        $rangeEnd = Carbon::parse($lastDay['date'].' '.$lastDay['close_time'], $timezone);

        $this->bookingHoldRepository->expirePast();

        $bookings = $this->bookingRepository->getOverlappingForStudio(
            $studioId,
            $rangeStart->toDateTimeString(),
            $rangeEnd->toDateTimeString(),
        );

        if ($excludeBookingId) {
            $bookings = $bookings->reject(fn ($booking) => (int) $booking->id === $excludeBookingId)->values();
        }

        $holds = $this->bookingHoldRepository->getActiveOverlappingForStudio(
            $studioId,
            $rangeStart->toDateTimeString(),
            $rangeEnd->toDateTimeString(),
        );

        if ($excludeHoldId) {
            $holds = $holds->reject(fn ($hold) => (int) $hold->id === $excludeHoldId)->values();
        }

        $blocks = $this->studioBlockRepository->getOverlappingForStudio(
            $studioId,
            $rangeStart->toDateTimeString(),
            $rangeEnd->toDateTimeString(),
        );

        // Daily ranges already lock full open→close windows; do not inflate overlaps with cleanup buffer.
        $conflict = $this->findConflict($rangeStart, $rangeEnd, $bookings, $holds, $blocks, 0, $timezone);

        if ($conflict !== null) {
            throw new BusinessException(
                'Selected date range is no longer available.',
                $conflict['code'],
                422,
                null,
                $conflict['context'],
            );
        }

        return [
            'start_at' => $rangeStart,
            'end_at' => $rangeEnd,
            'days_count' => $daysCount,
            'days' => $days,
        ];
    }

    /**
     * @return array{
     *     available: bool,
     *     start_date: string,
     *     end_date: string,
     *     days_count: int,
     *     days: list<array{date: string, open_time: string, close_time: string}>,
     *     start_at: string|null,
     *     end_at: string|null,
     *     reason: string|null
     * }
     */
    public function checkDailyAvailability(
        int $studioId,
        string $startDate,
        string $endDate,
        int $guestCount = 1,
    ): array {
        try {
            $result = $this->validateDailyRange($studioId, $startDate, $endDate, $guestCount);

            return [
                'available' => true,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days_count' => $result['days_count'],
                'days' => $result['days'],
                'start_at' => $result['start_at']->toDateTimeString(),
                'end_at' => $result['end_at']->toDateTimeString(),
                'reason' => null,
            ];
        } catch (BusinessException $e) {
            return [
                'available' => false,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days_count' => 0,
                'days' => [],
                'start_at' => null,
                'end_at' => null,
                'reason' => $e->getErrorCode(),
                'details' => $e->getContext(),
            ];
        }
    }

    /**
     * @return array{code: string, context: array<string, mixed>}|null
     */
    protected function findConflict(
        Carbon $start,
        Carbon $end,
        $bookings,
        $holds,
        $blocks,
        int $buffer,
        string $timezone,
    ): ?array {
        $conflictingBookings = [];
        foreach ($bookings as $booking) {
            $periodStart = $this->wallClock($booking->start_at, $timezone)->subMinutes($buffer);
            $periodEnd = $this->wallClock($booking->end_at, $timezone)->addMinutes($buffer);

            if ($start->lt($periodEnd) && $end->gt($periodStart)) {
                $conflictingBookings[] = (int) $booking->id;
            }
        }

        if ($conflictingBookings !== []) {
            return [
                'code' => 'conflicting_bookings',
                'context' => [
                    'reason' => 'conflicting_bookings',
                    'conflicting_booking_ids' => $conflictingBookings,
                ],
            ];
        }

        $conflictingHolds = [];
        foreach ($holds as $hold) {
            $periodStart = $this->wallClock($hold->start_at, $timezone);
            $periodEnd = $this->wallClock($hold->end_at, $timezone);

            if ($start->lt($periodEnd) && $end->gt($periodStart)) {
                $conflictingHolds[] = (int) $hold->id;
            }
        }

        if ($conflictingHolds !== []) {
            return [
                'code' => 'conflicting_holds',
                'context' => [
                    'reason' => 'conflicting_holds',
                    'conflicting_hold_ids' => $conflictingHolds,
                ],
            ];
        }

        $conflictingBlocks = [];
        foreach ($blocks as $block) {
            $periodStart = $this->wallClock($block->start_at, $timezone);
            $periodEnd = $this->wallClock($block->end_at, $timezone);

            if ($start->lt($periodEnd) && $end->gt($periodStart)) {
                $conflictingBlocks[] = (int) $block->id;
            }
        }

        if ($conflictingBlocks !== []) {
            return [
                'code' => 'conflicting_blocks',
                'context' => [
                    'reason' => 'conflicting_blocks',
                    'conflicting_block_ids' => $conflictingBlocks,
                ],
            ];
        }

        return null;
    }

    protected function buildOccupiedPeriods($bookings, $holds, $blocks, int $buffer, string $timezone): array
    {
        $periods = [];

        foreach ($bookings as $booking) {
            $periods[] = [
                'start' => $this->wallClock($booking->start_at, $timezone)->subMinutes($buffer),
                'end' => $this->wallClock($booking->end_at, $timezone)->addMinutes($buffer),
            ];
        }

        foreach ($holds as $hold) {
            $periods[] = [
                'start' => $this->wallClock($hold->start_at, $timezone),
                'end' => $this->wallClock($hold->end_at, $timezone),
            ];
        }

        foreach ($blocks as $block) {
            $periods[] = [
                'start' => $this->wallClock($block->start_at, $timezone),
                'end' => $this->wallClock($block->end_at, $timezone),
            ];
        }

        return $periods;
    }

    /**
     * Stored times are studio wall-clock values; Eloquent hydrates them in the app timezone,
     * so keep the clock reading and attach the studio timezone instead of converting.
     */
    protected function wallClock(mixed $value, string $timezone): Carbon
    {
        return $value instanceof \DateTimeInterface
            ? Carbon::instance($value)->shiftTimezone($timezone)
            : Carbon::parse($value, $timezone);
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
        $equipment = DB::table('equipment')
            ->where('id', $equipmentId)
            ->where('status', EquipmentStatus::Active->value)
            ->first(['quantity']);

        if (! $equipment) {
            return 0;
        }

        return $this->inventoryService->getEquipmentStock($studioId, $equipmentId, (int) $equipment->quantity, $start, $end)['available'];
    }

    protected function getAvailableHospitalityQuantity(int $studioId, int $hospitalityItemId, Carbon $start, Carbon $end): int
    {
        $item = DB::table('hospitality_items')
            ->where('id', $hospitalityItemId)
            ->where('is_active', true)
            ->first(['quantity']);

        if (! $item) {
            return 0;
        }

        return $this->inventoryService->getHospitalityStock($studioId, $hospitalityItemId, (int) $item->quantity, $start, $end)['available'];
    }
}
