<?php

namespace App\Services\Studio;

use App\Exceptions\BusinessException;
use App\Models\BookingStudioItem;
use App\Models\StudioItem;
use App\Repositories\Contracts\StudioRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StudioItemService
{
    public function __construct(
        protected StudioRepositoryInterface $studioRepository,
    ) {
    }

    public function listForStudio(int $studioId, bool $activeOnly = false): Collection
    {
        $this->studioRepository->findOrFail($studioId);

        $query = StudioItem::query()->where('studio_id', $studioId)->orderBy('id');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    public function findForStudio(int $studioId, int $id): StudioItem
    {
        $item = StudioItem::query()
            ->where('studio_id', $studioId)
            ->whereKey($id)
            ->first();

        if (! $item) {
            throw new BusinessException('Studio item not found.', 'studio_item_not_found', 404);
        }

        return $item;
    }

    public function create(int $studioId, array $data, ?string $imagePath = null): StudioItem
    {
        $this->studioRepository->findOrFail($studioId);
        $this->assertQuantities($data);

        return StudioItem::query()->create([
            'studio_id' => $studioId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'image_path' => $imagePath,
            'colors' => $this->normalizeColors($data['colors'] ?? null),
            'default_quantity' => (int) $data['default_quantity'],
            'min_quantity' => (int) ($data['min_quantity'] ?? 0),
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ]);
    }

    public function update(int $studioId, int $id, array $data, ?string $imagePath = null, bool $clearImage = false): StudioItem
    {
        $item = $this->findForStudio($studioId, $id);

        $merged = [
            'default_quantity' => $data['default_quantity'] ?? $item->default_quantity,
            'min_quantity' => $data['min_quantity'] ?? $item->min_quantity,
        ];
        $this->assertQuantities($merged);

        if (array_key_exists('colors', $data)) {
            $data['colors'] = $this->normalizeColors($data['colors']);
        }

        if ($clearImage) {
            if ($item->image_path) {
                Storage::disk('public')->delete($item->image_path);
            }
            $data['image_path'] = null;
        } elseif ($imagePath !== null) {
            if ($item->image_path) {
                Storage::disk('public')->delete($item->image_path);
            }
            $data['image_path'] = $imagePath;
        }

        $item->fill($data);
        $item->save();

        return $item->fresh();
    }

    public function delete(int $studioId, int $id): void
    {
        $item = $this->findForStudio($studioId, $id);

        if (BookingStudioItem::query()->where('studio_item_id', $item->id)->exists()) {
            throw new BusinessException('Cannot delete studio item used in bookings.', 'studio_item_in_use');
        }

        if ($item->image_path) {
            Storage::disk('public')->delete($item->image_path);
        }

        $item->delete();
    }

    /**
     * Resolve selections for quoting/booking.
     *
     * @param  list<array{id: int, quantity: int, color?: string|null}>|null  $requested
     * @return list<array{
     *   studio_item_id: int,
     *   name: string,
     *   quantity: int,
     *   default_quantity: int,
     *   min_quantity: int,
     *   unit_price: float,
     *   deduction_amount: float,
     *   color: string|null
     * }>
     */
    public function resolveForBooking(int $studioId, ?array $requested, ?int $packageId = null): array
    {
        if ($packageId !== null) {
            if ($requested !== null) {
                throw new BusinessException(
                    'Studio items cannot be changed on package bookings.',
                    'studio_items_not_allowed_for_package',
                );
            }

            return [];
        }

        $catalog = StudioItem::query()
            ->where('studio_id', $studioId)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $requestedById = [];
        foreach ($requested ?? [] as $row) {
            $requestedById[(int) $row['id']] = $row;
        }

        if ($requested !== null) {
            foreach ($requestedById as $id => $row) {
                if (! $catalog->has($id)) {
                    throw new BusinessException(
                        'Studio item is not available for this studio.',
                        'studio_item_invalid',
                        422,
                        null,
                        ['studio_item_id' => $id],
                    );
                }
            }
        }

        $resolved = [];

        foreach ($catalog as $item) {
            $selection = $requestedById[$item->id] ?? null;
            $quantity = $selection !== null ? (int) $selection['quantity'] : (int) $item->default_quantity;
            $color = $selection['color'] ?? null;

            if ($quantity < (int) $item->min_quantity) {
                throw new BusinessException(
                    'Studio item quantity is below the minimum.',
                    'studio_item_below_min',
                    422,
                    null,
                    [
                        'studio_item_id' => $item->id,
                        'min_quantity' => (int) $item->min_quantity,
                        'quantity' => $quantity,
                    ],
                );
            }

            if ($quantity > (int) $item->default_quantity) {
                throw new BusinessException(
                    'Studio item quantity cannot exceed the studio default.',
                    'studio_item_above_default',
                    422,
                    null,
                    [
                        'studio_item_id' => $item->id,
                        'default_quantity' => (int) $item->default_quantity,
                        'quantity' => $quantity,
                    ],
                );
            }

            $colors = is_array($item->colors) ? $item->colors : [];
            if ($color !== null && $color !== '') {
                if ($colors === []) {
                    $color = null;
                } elseif (! in_array($color, $colors, true)) {
                    throw new BusinessException(
                        'Invalid studio item color.',
                        'studio_item_color_invalid',
                        422,
                        null,
                        ['studio_item_id' => $item->id, 'color' => $color, 'colors' => $colors],
                    );
                }
            } else {
                $color = null;
            }

            $unitPrice = (float) $item->price;
            $removed = (int) $item->default_quantity - $quantity;
            $deduction = round($removed * $unitPrice, 2);

            $resolved[] = [
                'studio_item_id' => (int) $item->id,
                'name' => (string) $item->name,
                'quantity' => $quantity,
                'default_quantity' => (int) $item->default_quantity,
                'min_quantity' => (int) $item->min_quantity,
                'unit_price' => $unitPrice,
                'deduction_amount' => $deduction,
                'color' => $color,
            ];
        }

        return $resolved;
    }

    /**
     * @return list<array{
     *   studio_item_id: int,
     *   name: string,
     *   quantity: int,
     *   default_quantity: int,
     *   min_quantity: int,
     *   unit_price: float,
     *   deduction_amount: float,
     *   color: string|null
     * }>
     */
    public function snapshotsForBooking(int $bookingId): array
    {
        return BookingStudioItem::query()
            ->with('studioItem:id,name')
            ->where('booking_id', $bookingId)
            ->get()
            ->map(fn (BookingStudioItem $row) => [
                'studio_item_id' => (int) $row->studio_item_id,
                'name' => (string) ($row->studioItem?->name ?? 'Studio item'),
                'quantity' => (int) $row->quantity,
                'default_quantity' => (int) $row->default_quantity,
                'min_quantity' => (int) $row->min_quantity,
                'unit_price' => (float) $row->unit_price,
                'deduction_amount' => (float) $row->deduction_amount,
                'color' => $row->color,
            ])
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $resolved
     */
    public function persistForBooking(int $bookingId, array $resolved): void
    {
        $now = now();

        foreach ($resolved as $row) {
            DB::table('booking_studio_items')->insert([
                'booking_id' => $bookingId,
                'studio_item_id' => $row['studio_item_id'],
                'quantity' => $row['quantity'],
                'default_quantity' => $row['default_quantity'],
                'min_quantity' => $row['min_quantity'],
                'unit_price' => $row['unit_price'],
                'deduction_amount' => $row['deduction_amount'],
                'color' => $row['color'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $resolved
     * @return list<array<string, mixed>>
     */
    public function toLineItems(array $resolved): array
    {
        $lines = [];

        foreach ($resolved as $row) {
            if ((float) $row['deduction_amount'] <= 0) {
                continue;
            }

            $removed = (int) $row['default_quantity'] - (int) $row['quantity'];
            $lines[] = [
                'item_type' => 'studio_item_deduction',
                'item_id' => (int) $row['studio_item_id'],
                'description' => $row['name'].' removed (×'.$removed.')',
                'quantity' => $removed,
                'unit_price' => (float) $row['unit_price'],
                'discount_amount' => 0,
                'total_price' => -round((float) $row['deduction_amount'], 2),
                'included' => false,
            ];
        }

        return $lines;
    }

    /**
     * @param  array{default_quantity?: int, min_quantity?: int}  $data
     */
    protected function assertQuantities(array $data): void
    {
        $default = (int) ($data['default_quantity'] ?? 0);
        $min = (int) ($data['min_quantity'] ?? 0);

        if ($default < 0 || $min < 0) {
            throw new BusinessException('Quantities cannot be negative.', 'invalid_quantity');
        }

        if ($min > $default) {
            throw new BusinessException(
                'Minimum quantity cannot exceed default quantity.',
                'studio_item_min_exceeds_default',
            );
        }
    }

    /**
     * @param  mixed  $colors
     * @return list<string>|null
     */
    protected function normalizeColors(mixed $colors): ?array
    {
        if ($colors === null || $colors === '') {
            return null;
        }

        if (is_string($colors)) {
            $decoded = json_decode($colors, true);
            $colors = is_array($decoded) ? $decoded : [$colors];
        }

        if (! is_array($colors)) {
            return null;
        }

        $normalized = array_values(array_unique(array_filter(array_map(
            fn ($c) => is_string($c) ? trim($c) : null,
            $colors,
        ))));

        return $normalized === [] ? null : $normalized;
    }
}
