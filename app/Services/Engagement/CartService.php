<?php

namespace App\Services\Engagement;

use App\Enums\EquipmentStatus;
use App\Exceptions\BusinessException;
use App\Models\Accessory;
use App\Models\CartItem;
use App\Models\Equipment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CartService
{
    public function listForUser(int $userId): Collection
    {
        return CartItem::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->get()
            ->each(fn (CartItem $item) => $item->setRelation('product', $this->loadProduct($item)));
    }

    public function addOrUpdate(int $userId, string $type, int $id, int $quantity): CartItem
    {
        $this->assertCartable($type, $id);

        if ($quantity < 1) {
            throw new BusinessException('Quantity must be at least 1.', 'invalid_quantity');
        }

        $item = CartItem::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'item_type' => $type,
                'item_id' => $id,
            ],
            ['quantity' => $quantity],
        );

        $item->setRelation('product', $this->loadProduct($item));

        return $item;
    }

    public function setQuantity(int $userId, string $type, int $id, int $quantity): CartItem
    {
        $item = $this->findOwned($userId, $type, $id);

        if ($quantity < 1) {
            throw new BusinessException('Quantity must be at least 1.', 'invalid_quantity');
        }

        $item->update(['quantity' => $quantity]);
        $item->setRelation('product', $this->loadProduct($item));

        return $item->fresh() ?? $item;
    }

    public function remove(int $userId, string $type, int $id): void
    {
        $this->findOwned($userId, $type, $id)->delete();
    }

    public function clear(int $userId): void
    {
        CartItem::query()->where('user_id', $userId)->delete();
    }

    protected function findOwned(int $userId, string $type, int $id): CartItem
    {
        $item = CartItem::query()
            ->where('user_id', $userId)
            ->where('item_type', $type)
            ->where('item_id', $id)
            ->first();

        if (! $item) {
            throw new BusinessException('Cart item not found.', 'cart_item_not_found', 404);
        }

        return $item;
    }

    protected function assertCartable(string $type, int $id): void
    {
        if (! in_array($type, CartItem::TYPES, true)) {
            throw new BusinessException('Invalid cart item type.', 'cart_type_invalid');
        }

        $exists = match ($type) {
            CartItem::TYPE_EQUIPMENT => Equipment::query()
                ->whereKey($id)
                ->where('status', EquipmentStatus::Active)
                ->exists(),
            CartItem::TYPE_ACCESSORY => Accessory::query()->whereKey($id)->where('is_active', true)->exists(),
            default => false,
        };

        if (! $exists) {
            throw new BusinessException('Item not found or not available.', 'cart_target_invalid', 404);
        }
    }

    protected function loadProduct(CartItem $item): ?Model
    {
        return match ($item->item_type) {
            CartItem::TYPE_EQUIPMENT => Equipment::query()->with('images')->find($item->item_id),
            CartItem::TYPE_ACCESSORY => Accessory::query()->with('images')->find($item->item_id),
            default => null,
        };
    }
}
