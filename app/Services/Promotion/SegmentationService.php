<?php

namespace App\Services\Promotion;

use App\Exceptions\BusinessException;
use App\Repositories\Contracts\SegmentRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SegmentationService
{
    public function __construct(
        protected SegmentRepositoryInterface $segmentRepository,
        protected UserRepositoryInterface $userRepository,
    ) {
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->segmentRepository->paginate($perPage);
    }

    public function create(array $data): Model
    {
        return $this->segmentRepository->create($data);
    }

    public function update(int $id, array $data): Model
    {
        return $this->segmentRepository->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->segmentRepository->delete($id);
    }

    public function evaluateSegment(int $userId, int $segmentId): bool
    {
        $segment = $this->segmentRepository->findOrFail($segmentId);

        if (! $segment->is_active) {
            return false;
        }

        $filters = is_string($segment->filters_json)
            ? json_decode($segment->filters_json, true)
            : (array) $segment->filters_json;

        return $this->userMatchesFilters($userId, $filters);
    }

    public function previewCount(int $segmentId): int
    {
        $segment = $this->segmentRepository->findOrFail($segmentId);
        $filters = is_string($segment->filters_json)
            ? json_decode($segment->filters_json, true)
            : (array) $segment->filters_json;

        return $this->buildFilteredQuery($filters)->count();
    }

    /**
     * @return Collection<int, int>
     */
    public function getUsersForSegment(int $segmentId): Collection
    {
        $segment = $this->segmentRepository->findOrFail($segmentId);

        if (! $segment->is_active) {
            throw new BusinessException('Segment is not active.', 'segment_inactive');
        }

        $filters = is_string($segment->filters_json)
            ? json_decode($segment->filters_json, true)
            : (array) $segment->filters_json;

        return $this->buildFilteredQuery($filters)->pluck('id');
    }

    protected function userMatchesFilters(int $userId, array $filters): bool
    {
        return $this->buildFilteredQuery($filters)->where('users.id', $userId)->exists();
    }

    protected function buildFilteredQuery(array $filters): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('users');

        if ($filters['status'] ?? null) {
            $query->where('users.status', $filters['status']);
        }

        if ($filters['booked_before'] ?? false) {
            $query->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('bookings')
                    ->whereColumn('bookings.user_id', 'users.id');
            });
        }

        if ($filters['never_booked'] ?? false) {
            $query->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('bookings')
                    ->whereColumn('bookings.user_id', 'users.id');
            });
        }

        if ($filters['studio_id'] ?? null) {
            $query->whereExists(function ($q) use ($filters) {
                $q->select(DB::raw(1))
                    ->from('bookings')
                    ->whereColumn('bookings.user_id', 'users.id')
                    ->where('bookings.studio_id', $filters['studio_id']);
            });
        }

        if ($filters['min_booking_count'] ?? null) {
            $query->whereIn('users.id', function ($q) use ($filters) {
                $q->select('user_id')
                    ->from('bookings')
                    ->groupBy('user_id')
                    ->havingRaw('COUNT(*) >= ?', [(int) $filters['min_booking_count']]);
            });
        }

        if ($filters['min_total_spend'] ?? null) {
            $query->whereIn('users.id', function ($q) use ($filters) {
                $q->select('user_id')
                    ->from('bookings')
                    ->where('status', 'completed')
                    ->groupBy('user_id')
                    ->havingRaw('SUM(total_amount) >= ?', [(float) $filters['min_total_spend']]);
            });
        }

        if ($filters['last_booking_before'] ?? null) {
            $date = Carbon::parse($filters['last_booking_before']);
            $query->whereIn('users.id', function ($q) use ($date) {
                $q->select('user_id')
                    ->from('bookings')
                    ->groupBy('user_id')
                    ->havingRaw('MAX(start_at) < ?', [$date]);
            });
        }

        if ($filters['inactive_days'] ?? null) {
            $cutoff = Carbon::now()->subDays((int) $filters['inactive_days']);
            $query->where(function ($q) use ($cutoff) {
                $q->whereNull('users.last_login_at')
                    ->orWhere('users.last_login_at', '<', $cutoff);
            });
        }

        if ($filters['min_points_balance'] ?? null) {
            $query->whereIn('users.id', function ($q) use ($filters) {
                $q->select('user_id')
                    ->from('points_transactions')
                    ->groupBy('user_id')
                    ->havingRaw('SUM(points) >= ?', [(float) $filters['min_points_balance']]);
            });
        }

        if ($filters['never_redeemed'] ?? false) {
            $query->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('points_transactions')
                    ->whereColumn('points_transactions.user_id', 'users.id')
                    ->where('points_transactions.type', 'redemption');
            });
        }

        return $query;
    }
}
