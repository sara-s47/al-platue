<?php

namespace App\Repositories\Eloquent;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EloquentNotificationRepository extends BaseRepository implements NotificationRepositoryInterface
{
    public function __construct(Notification $model)
    {
        parent::__construct($model);
    }

    public function findDueScheduled(): Collection
    {
        return $this->model->newQuery()
            ->where('status', NotificationStatus::Scheduled->value)
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->get();
    }

    public function listForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->whereHas('recipients', fn ($query) => $query->where('user_id', $userId))
            ->with(['recipients' => fn ($query) => $query->where('user_id', $userId)])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
