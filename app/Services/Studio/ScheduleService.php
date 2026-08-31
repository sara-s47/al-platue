<?php

namespace App\Services\Studio;

use App\Exceptions\BusinessException;
use App\Models\StudioBlock;
use App\Models\StudioSchedule;
use App\Models\StudioScheduleOverride;
use App\Repositories\Contracts\StudioBlockRepositoryInterface;
use App\Repositories\Contracts\StudioRepositoryInterface;
use App\Repositories\Contracts\StudioScheduleOverrideRepositoryInterface;
use App\Repositories\Contracts\StudioScheduleRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScheduleService
{
    public function __construct(
        protected StudioRepositoryInterface $studioRepository,
        protected StudioScheduleRepositoryInterface $scheduleRepository,
        protected StudioScheduleOverrideRepositoryInterface $overrideRepository,
        protected StudioBlockRepositoryInterface $blockRepository,
    ) {
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
                'source' => 'weekly',
                'reason' => null,
            ];
        }

        return [
            'studio_id' => $studioId,
            'date' => $carbonDate->toDateString(),
            'is_closed' => $weekly->is_closed,
            'open_time' => $weekly->is_closed ? null : $this->formatTime($weekly->open_time),
            'close_time' => $weekly->is_closed ? null : $this->formatTime($weekly->close_time),
            'source' => 'weekly',
            'reason' => null,
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

            return $schedules;
        });
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

    public function deleteOverride(int $overrideId): bool
    {
        return $this->overrideRepository->delete($overrideId);
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

    public function deleteBlock(int $blockId): bool
    {
        return $this->blockRepository->delete($blockId);
    }

    protected function formatTime(mixed $time): ?string
    {
        if ($time === null) {
            return null;
        }

        return Carbon::parse($time)->format('H:i:s');
    }
}
