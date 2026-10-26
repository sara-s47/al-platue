<?php

namespace App\Services\Engagement;

use App\Enums\EquipmentStatus;
use App\Exceptions\BusinessException;
use App\Models\Accessory;
use App\Models\Equipment;
use App\Models\Favorite;
use App\Models\Studio;
use App\Repositories\Contracts\FavoriteRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class FavoriteService
{
    public function __construct(
        protected FavoriteRepositoryInterface $favoriteRepository,
    ) {
    }

    public function listForUser(int $userId, int $perPage = 15, ?string $type = null): LengthAwarePaginator
    {
        $query = Favorite::query()
            ->with(['favoritable' => function ($morphTo) {
                $morphTo->morphWith([
                    Studio::class => ['images', 'category', 'pricingRules'],
                    Equipment::class => ['images'],
                    Accessory::class => ['images'],
                ]);
            }])
            ->where('user_id', $userId)
            ->latest('id');

        if ($type !== null) {
            $query->where('favoritable_type', $type);
        }

        return $query->paginate($perPage);
    }

    public function add(int $userId, string $type, int $id): Model
    {
        $this->assertFavoritable($type, $id);

        $existing = Favorite::query()
            ->where('user_id', $userId)
            ->where('favoritable_type', $type)
            ->where('favoritable_id', $id)
            ->first();

        if ($existing) {
            throw new BusinessException('Already in favorites.', 'favorite_exists');
        }

        $favorite = $this->favoriteRepository->create([
            'user_id' => $userId,
            'favoritable_type' => $type,
            'favoritable_id' => $id,
        ]);

        return $favorite->load(['favoritable' => function ($morphTo) {
            $morphTo->morphWith([
                Studio::class => ['images', 'category', 'pricingRules'],
                Equipment::class => ['images'],
                Accessory::class => ['images'],
            ]);
        }]);
    }

    public function remove(int $userId, string $type, int $id): bool
    {
        $favorite = Favorite::query()
            ->where('user_id', $userId)
            ->where('favoritable_type', $type)
            ->where('favoritable_id', $id)
            ->first();

        if (! $favorite) {
            throw new BusinessException('Favorite not found.', 'favorite_not_found', 404);
        }

        return $this->favoriteRepository->delete($favorite->id);
    }

    protected function assertFavoritable(string $type, int $id): void
    {
        if (! in_array($type, Favorite::TYPES, true)) {
            throw new BusinessException('Invalid favorite type.', 'favorite_type_invalid');
        }

        $exists = match ($type) {
            Favorite::TYPE_STUDIO => Studio::query()->whereKey($id)->where('is_active', true)->exists(),
            Favorite::TYPE_EQUIPMENT => Equipment::query()
                ->whereKey($id)
                ->where('status', EquipmentStatus::Active)
                ->exists(),
            Favorite::TYPE_ACCESSORY => Accessory::query()->whereKey($id)->where('is_active', true)->exists(),
            default => false,
        };

        if (! $exists) {
            throw new BusinessException('Item not found or not available.', 'favorite_target_invalid', 404);
        }
    }
}
