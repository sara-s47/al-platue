<?php

namespace App\Services\Engagement;

use App\Exceptions\BusinessException;
use App\Repositories\Contracts\FavoriteRepositoryInterface;
use App\Repositories\Contracts\StudioRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class FavoriteService
{
    public function __construct(
        protected FavoriteRepositoryInterface $favoriteRepository,
        protected StudioRepositoryInterface $studioRepository,
    ) {
    }

    public function listForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return \App\Models\Favorite::query()->with('studio')->where('user_id', $userId)->latest()->paginate($perPage);
    }

    public function add(int $userId, int $studioId): Model
    {
        $this->studioRepository->findOrFail($studioId);

        $existing = \App\Models\Favorite::query()
            ->where('user_id', $userId)
            ->where('studio_id', $studioId)
            ->first();

        if ($existing) {
            throw new BusinessException('Studio already in favorites.', 'favorite_exists');
        }

        return $this->favoriteRepository->create([
            'user_id' => $userId,
            'studio_id' => $studioId,
        ]);
    }

    public function remove(int $userId, int $studioId): bool
    {
        $favorite = \App\Models\Favorite::query()
            ->where('user_id', $userId)
            ->where('studio_id', $studioId)
            ->first();

        if (! $favorite) {
            throw new BusinessException('Favorite not found.', 'favorite_not_found', 404);
        }

        return $this->favoriteRepository->delete($favorite->id);
    }
}