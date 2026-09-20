<?php

namespace App\Services\Studio;

use App\Exceptions\BusinessException;
use App\Models\StudioBlock;
use App\Models\StudioScheduleOverride;
use App\Repositories\Contracts\StudioBlockRepositoryInterface;
use App\Repositories\Contracts\StudioRepositoryInterface;
use App\Repositories\Contracts\StudioScheduleOverrideRepositoryInterface;
use App\Repositories\Contracts\StudioScheduleRepositoryInterface;
use App\Services\Pricing\PricingService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScheduleService
{
    private const WEEKEND_DAYS = [5, 6]; // Friday, Saturday

    public function __construct(
        protected StudioRepositoryInterface $studioRepository,
        protected StudioScheduleRepositoryInterface $scheduleRepository,
        protected StudioScheduleOverrideRepositoryInterface $overrideRepository,
        protected StudioBlockRepositoryInterface $blockRepository,
        protected PricingService $pricingService,
    ) {
    }

    public function getWeeklySchedule(int $studioId): Collection
    {
        $this->studioRepository->findOrFail($studioId);

        return $this->scheduleRepository->getForStudio($studioId)->map(fn ($day) => [
            'id' => $day->id,
            'day_of_week' => (int) $day->day_of_week,
            'open_time' => $day->is_closed ? null : $this->formatTime($day->open_time),
            'close_time' => $day->is_closed ? null : $this->formatTime($day->close_time),
            'is_closed' => (bool) $day->is_closed,
            'is_weekend' => in_array((int) $day->day_of_week, self::WEEKEND_DAYS, true),
        ]);
    }

    public function getEffectiveSchedule(int $studioId, string $date): array
    {
        $this->studioRepository->findOrFail($studioId);

        $carbonDate = Carbon::parse($date);
        $override = $this->overrideRepository->findForStudioAndDate($studioId, $carbonDate->toDateString());

        if ($override) {
            return [
                'studio_id' => $studioId,
                'date' => $carbonDate->toDateString(),
                'is_closed' => $override->is_closed,
                'open_time' => $override->is_closed ? null : $this->formatTime($override->open_time),
                'close_time' => $override->is_closed ? null : $this->formatTime($override->close_time),
                'source' => 'override',
                'override_id' => $override->id,
                'reason' => $override->reason,
            ];
        }

        $weekly = $this->scheduleRepository->findForStudioAndDay($studioId, $carbonDate->dayOfWeek);

        if (! $weekly) {
            return [
                'studio_id' => $studioId,
                'date' => $carbonDate->toDateString(),
                'is_closed' => true,
                'open_time' => null,
                'close_time' => null,
                'source' => 'closed',
                'override_id' => null,
                'reason' => null,
            ];
        }

        return [
            'studio_id' => $studioId,
            'date' => $carbonDate->toDateString(),
            'is_closed' => $weekly->is_closed,
            'open_time' => $weekly->is_closed ? null : $this->formatTime($weekly->open_time),
            'close_time' => $weekly->is_closed ? null : $this->formatTime($weekly->close_time),
            'source' => $weekly->is_closed ? 'closed' : 'weekly',
            'override_id' => null,
            'reason' => null,
        ];
    }

    public function getDayDetails(int $studioId, string $date): array
    {
        $carbonDate = Carbon::parse($date);
        $schedule = $this->getEffectiveSchedule($studioId, $carbonDate->toDateString());
        $dayOfWeek = (int) $carbonDate->dayOfWeek;
        $isWeekend = in_array($dayOfWeek, self::WEEKEND_DAYS, true);

        $dayStart = $carbonDate->copy()->startOfDay();
        $dayEnd = $carbonDate->copy()->endOfDay();

        $blocks = $this->blockRepository
            ->getOverlappingForStudio($studioId, $dayStart->toDateTimeString(), $dayEnd->toDateTimeString())
            ->map(fn ($block) => [
                'id' => $block->id,
                'start_at' => Carbon::parse($block->start_at)->toIso8601String(),
                'end_at' => Carbon::parse($block->end_at)->toIso8601String(),
                'reason' => $block->reason,
            ])
            ->values()
            ->all();

        $pricing = $this->pricingService->resolvePricingForDate($studioId, $carbonDate->toDateString());

        return [
            'date' => $carbonDate->toDateString(),
            'day_of_week' => $dayOfWeek,
            'is_weekend' => $isWeekend,
            'schedule' => [
                'source' => $schedule['source'],
                'open_time' => $schedule['open_time'],
                'close_time' => $schedule['close_time'],
                'is_closed' => $schedule['is_closed'],
                'override_id' => $schedule['override_id'] ?? null,
                'reason' => $schedule['reason'] ?? null,
            ],
            'blocks' => $blocks,
            'pricing' => $pricing,
        ];
    }

    public function getOverview(int $studioId, ?string $from = null, ?string $to = null): array
    {
        $this->studioRepository->findOrFail($studioId);

        $fromDate = Carbon::parse($from ?? now()->toDateString())->startOfDay();
        $toDate = Carbon::parse($to ?? $fromDate->copy()->addDays(13)->toDateString())->startOfDay();

        if ($toDate->lt($fromDate)) {
            throw new BusinessException('End date must be on or after start date.', 'invalid_date_range');
        }

        if ($fromDate->diffInDays($toDate) > 62) {
            throw new BusinessException('Overview range cannot exceed 62 days.', 'invalid_date_range');
        }

        $days = [];
        $cursor = $fromDate->copy();

        while ($cursor->lte($toDate)) {
            $days[] = $this->getDayDetails($studioId, $cursor->toDateString());
            $cursor->addDay();
        }

        return [
            'studio_id' => $studioId,
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'weekly_schedule' => $this->getWeeklySchedule($studioId),
            'days' => $days,
        ];
    }

    public function setWeeklySchedule(int $studioId, array $days): Collection
    {
        $this->studioRepository->findOrFail($studioId);

        return DB::transaction(function () use ($studioId, $days) {
            $schedules = collect();

            foreach ($days as $day) {
                $existing = $this->scheduleRepository->findForStudioAndDay($studioId, (int) $day['day_of_week']);

                $payload = [
                    'studio_id' => $studioId,
                    'day_of_week' => (int) $day['day_of_week'],
                    'open_time' => $day['is_closed'] ?? false ? null : ($day['open_time'] ?? null),
                    'close_time' => $day['is_closed'] ?? false ? null : ($day['close_time'] ?? null),
                    'is_closed' => (bool) ($day['is_closed'] ?? false),
                ];

                if ($existing) {
                    $schedules->push($this->scheduleRepository->update($existing->id, $payload));
                } else {
                    $schedules->push($this->scheduleRepository->create($payload));
                }
            }

            return $this->getWeeklySchedule($studioId);
        });
    }

    public function paginateOverrides(int $studioId, int $perPage = 15): LengthAwarePaginator
    {
        $this->studioRepository->findOrFail($studioId);

        return StudioScheduleOverride::query()
            ->where('studio_id', $studioId)
            ->orderByDesc('date')
            ->paginate($perPage);
    }

    public function createOverride(int $studioId, array $data): StudioScheduleOverride
    {
        $this->studioRepository->findOrFail($studioId);

        $existing = $this->overrideRepository->findForStudioAndDate($studioId, $data['date']);

        if ($existing) {
            throw new BusinessException('Schedule override already exists for this date.', 'override_exists');
        }

        return $this->overrideRepository->create([
            'studio_id' => $studioId,
            'date' => $data['date'],
            'is_closed' => (bool) ($data['is_closed'] ?? false),
            'open_time' => ($data['is_closed'] ?? false) ? null : ($data['open_time'] ?? null),
            'close_time' => ($data['is_closed'] ?? false) ? null : ($data['close_time'] ?? null),
            'reason' => $data['reason'] ?? null,
        ]);
    }

    public function updateOverride(int $studioId, int $overrideId, array $data): StudioScheduleOverride
    {
        $override = $this->overrideRepository->findOrFail($overrideId);

        if ((int) $override->studio_id !== $studioId) {
            throw new BusinessException('Override does not belong to this studio.', 'override_studio_mismatch', 404);
        }

        if (isset($data['date'])) {
            $existing = $this->overrideRepository->findForStudioAndDate($studioId, $data['date']);
            if ($existing && (int) $existing->id !== $overrideId) {
                throw new BusinessException('Schedule override already exists for this date.', 'override_exists');
            }
        }

        $isClosed = array_key_exists('is_closed', $data)
            ? (bool) $data['is_closed']
            : (bool) $override->is_closed;

        $payload = array_filter([
            'date' => $data['date'] ?? null,
            'is_closed' => array_key_exists('is_closed', $data) ? $isClosed : null,
            'reason' => array_key_exists('reason', $data) ? $data['reason'] : null,
        ], fn ($value) => $value !== null);

        $payload['open_time'] = $isClosed ? null : ($data['open_time'] ?? $override->open_time);
        $payload['close_time'] = $isClosed ? null : ($data['close_time'] ?? $override->close_time);

        return $this->overrideRepository->update($overrideId, $payload);
    }

    public function deleteOverride(int $overrideId): bool
    {
        return $this->overrideRepository->delete($overrideId);
    }

    public function paginateBlocks(int $studioId, int $perPage = 15): LengthAwarePaginator
    {
        $this->studioRepository->findOrFail($studioId);

        return StudioBlock::query()
            ->where('studio_id', $studioId)
            ->orderByDesc('start_at')
            ->paginate($perPage);
    }

    public function createBlock(int $studioId, array $data): StudioBlock
    {
        $this->studioRepository->findOrFail($studioId);

        if (Carbon::parse($data['start_at'])->gte(Carbon::parse($data['end_at']))) {
            throw new BusinessException('Block end time must be after start time.', 'invalid_block_range');
        }

        return $this->blockRepository->create([
            'studio_id' => $studioId,
            'start_at' => $data['start_at'],
            'end_at' => $data['end_at'],
            'reason' => $data['reason'] ?? null,
            'created_by' => $data['created_by'],
        ]);
    }

    public function updateBlock(int $studioId, int $blockId, array $data): StudioBlock
    {
        $block = $this->blockRepository->findOrFail($blockId);

        if ((int) $block->studio_id !== $studioId) {
            throw new BusinessException('Block does not belong to this studio.', 'block_studio_mismatch', 404);
        }

        $startAt = $data['start_at'] ?? $block->start_at;
        $endAt = $data['end_at'] ?? $block->end_at;

        if (Carbon::parse($startAt)->gte(Carbon::parse($endAt))) {
            throw new BusinessException('Block end time must be after start time.', 'invalid_block_range');
        }

        return $this->blockRepository->update($blockId, [
            'start_at' => $startAt,
            'end_at' => $endAt,
            'reason' => array_key_exists('reason', $data) ? $data['reason'] : $block->reason,
        ]);
    }

    public function deleteBlock(int $blockId): bool
    {
        return $this->blockRepository->delete($blockId);
    }

    protected function formatTime(mixed $time): ?string
    {
        if ($time === null) {
            return null;
        }

        return Carbon::parse($time)->format('H:i');
    }
}
