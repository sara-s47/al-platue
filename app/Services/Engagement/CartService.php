<?php

namespace App\Services\Engagement;

use App\Enums\BookingMode;
use App\Enums\EquipmentStatus;
use App\Exceptions\BusinessException;
use App\Models\Accessory;
use App\Models\Caravan;
use App\Models\CartItem;
use App\Models\Equipment;
use App\Models\Generator;
use App\Models\PortableBathroom;
use App\Models\Studio;
use App\Models\User;
use App\Services\Studio\HoldService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CartService
{
    public function __construct(protected HoldService $holdService)
    {
    }

    public function listForUser(int $userId): Collection
    {
        return CartItem::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->get()
            ->each(fn (CartItem $item) => $item->setRelation('product', $this->loadProduct($item)));
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function addOrUpdate(int $userId, string $type, int $id, int $quantity, ?array $meta = null): CartItem
    {
        $this->assertCartable($type, $id);

        if ($type === CartItem::TYPE_STUDIO) {
            return $this->upsertStudioLine($userId, $id, $meta ?? []);
        }

        if ($quantity < 1) {
            throw new BusinessException('Quantity must be at least 1.', 'invalid_quantity');
        }

        $item = CartItem::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'item_type' => $type,
                'item_id' => $id,
            ],
            [
                'quantity' => $quantity,
                'meta' => $meta,
            ],
        );

        $item->setRelation('product', $this->loadProduct($item));

        return $item;
    }

    public function setQuantity(int $userId, string $type, int $id, int $quantity, ?array $meta = null): CartItem
    {
        if ($type === CartItem::TYPE_STUDIO) {
            $item = $this->findOwned($userId, $type, $id);

            return $this->upsertStudioLine($userId, $id, array_merge((array) ($item->meta ?? []), $meta ?? []));
        }

        $item = $this->findOwned($userId, $type, $id);

        if ($quantity < 1) {
            throw new BusinessException('Quantity must be at least 1.', 'invalid_quantity');
        }

        $payload = ['quantity' => $quantity];
        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        $item->update($payload);
        $item->setRelation('product', $this->loadProduct($item));

        return $item->fresh() ?? $item;
    }

    public function remove(int $userId, string $type, int $id): void
    {
        $item = $this->findOwned($userId, $type, $id);

        if ($type === CartItem::TYPE_STUDIO) {
            $holdId = (int) (($item->meta['hold_id'] ?? 0));
            if ($holdId > 0) {
                try {
                    $this->holdService->releaseHold($holdId, User::query()->findOrFail($userId));
                } catch (\Throwable) {
                    // hold may already be expired/cancelled
                }
            }
        }

        $item->delete();
    }

    public function clear(int $userId): void
    {
        $studio = CartItem::query()
            ->where('user_id', $userId)
            ->where('item_type', CartItem::TYPE_STUDIO)
            ->first();

        if ($studio) {
            $holdId = (int) (($studio->meta['hold_id'] ?? 0));
            if ($holdId > 0) {
                try {
                    $this->holdService->releaseHold($holdId, User::query()->findOrFail($userId));
                } catch (\Throwable) {
                }
            }
        }

        CartItem::query()->where('user_id', $userId)->delete();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function upsertStudioLine(int $userId, int $studioId, array $meta): CartItem
    {
        $mode = BookingMode::tryFrom((string) ($meta['booking_mode'] ?? BookingMode::Hourly->value))
            ?? BookingMode::Hourly;

        $holdPayload = [
            'studio_id' => $studioId,
            'booking_mode' => $mode->value,
            'guest_count' => (int) ($meta['guest_count'] ?? 1),
        ];

        if ($mode === BookingMode::Daily) {
            if (empty($meta['start_date']) || empty($meta['end_date'])) {
                throw new BusinessException('start_date and end_date are required for daily studio cart lines.', 'studio_dates_required');
            }
            $holdPayload['start_date'] = $meta['start_date'];
            $holdPayload['end_date'] = $meta['end_date'];
        } else {
            if (empty($meta['start_at']) || empty($meta['end_at'])) {
                throw new BusinessException('start_at and end_at are required for hourly studio cart lines.', 'studio_times_required');
            }
            $holdPayload['start_at'] = $meta['start_at'];
            $holdPayload['end_at'] = $meta['end_at'];
            if (! empty($meta['package_id'])) {
                $holdPayload['package_id'] = (int) $meta['package_id'];
            }
        }

        // One studio line per cart.
        CartItem::query()
            ->where('user_id', $userId)
            ->where('item_type', CartItem::TYPE_STUDIO)
            ->where('item_id', '!=', $studioId)
            ->get()
            ->each(function (CartItem $old) use ($userId) {
                $holdId = (int) (($old->meta['hold_id'] ?? 0));
                if ($holdId > 0) {
                    try {
                        $this->holdService->releaseHold($holdId, User::query()->findOrFail($userId));
                    } catch (\Throwable) {
                    }
                }
                $old->delete();
            });

        $existing = CartItem::query()
            ->where('user_id', $userId)
            ->where('item_type', CartItem::TYPE_STUDIO)
            ->where('item_id', $studioId)
            ->first();

        if ($existing && ! empty($existing->meta['hold_id'])) {
            try {
                $this->holdService->releaseHold((int) $existing->meta['hold_id'], User::query()->findOrFail($userId));
            } catch (\Throwable) {
            }
        }

        $user = User::query()->findOrFail($userId);
        $hold = $this->holdService->createHold($user, $holdPayload);

        $storedMeta = array_merge($meta, [
            'booking_mode' => $mode->value,
            'hold_id' => $hold->id,
            'guest_count' => (int) ($meta['guest_count'] ?? 1),
        ]);

        if (! empty($meta['paint_color_id'])) {
            $storedMeta['paint_color_id'] = (int) $meta['paint_color_id'];
        }
        if (! empty($meta['package_id'])) {
            $storedMeta['package_id'] = (int) $meta['package_id'];
        }

        $item = CartItem::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'item_type' => CartItem::TYPE_STUDIO,
                'item_id' => $studioId,
            ],
            [
                'quantity' => 1,
                'meta' => $storedMeta,
            ],
        );

        $item->setRelation('product', $this->loadProduct($item));

        return $item;
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
            CartItem::TYPE_STUDIO => Studio::query()->whereKey($id)->where('is_active', true)->exists(),
            CartItem::TYPE_PORTABLE_BATHROOM => PortableBathroom::query()->whereKey($id)->where('is_active', true)->exists(),
            CartItem::TYPE_CARAVAN => Caravan::query()->whereKey($id)->where('is_active', true)->exists(),
            CartItem::TYPE_GENERATOR => Generator::query()->whereKey($id)->where('is_active', true)->exists(),
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
            CartItem::TYPE_STUDIO => Studio::query()->with(['images', 'paintColors'])->find($item->item_id),
            CartItem::TYPE_PORTABLE_BATHROOM => PortableBathroom::query()->with('images')->find($item->item_id),
            CartItem::TYPE_CARAVAN => Caravan::query()->with('images')->find($item->item_id),
            CartItem::TYPE_GENERATOR => Generator::query()->with('images')->find($item->item_id),
            default => null,
        };
    }
}
