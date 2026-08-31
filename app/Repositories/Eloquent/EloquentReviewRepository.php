<?php

namespace App\Repositories\Eloquent;

use App\Models\Review;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class EloquentReviewRepository extends BaseRepository implements ReviewRepositoryInterface
{
    public function __construct(Review $model)
    {
        parent::__construct($model);
    }

    public function findByBookingId(int $bookingId): ?Model
    {
        return $this->model->newQuery()->where('booking_id', $bookingId)->first();
    }

    public function listForStudio(int $studioId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->newQuery()
            ->where('studio_id', $studioId)
            ->with(['user', 'dimensionScores.dimension'])
            ->orderByDesc('created_at');

        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }

        if ($filters['min_rating'] ?? null) {
            $query->where('rating', '>=', (float) $filters['min_rating']);
        }

        return $query->paginate($perPage);
    }

    public function paginateAdmin(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->newQuery()
            ->with(['user', 'studio', 'dimensionScores.dimension'])
            ->orderByDesc('created_at');

        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }

        if ($filters['studio_id'] ?? null) {
            $query->where('studio_id', $filters['studio_id']);
        }

        return $query->paginate($perPage);
    }

    public function listForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->with(['studio', 'dimensionScores.dimension'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
