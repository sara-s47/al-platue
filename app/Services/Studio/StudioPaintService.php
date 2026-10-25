<?php

namespace App\Services\Studio;

use App\Exceptions\BusinessException;
use App\Models\Studio;
use App\Models\StudioPaintColor;
use Illuminate\Support\Facades\DB;

class StudioPaintService
{
    /**
     * @return array{surcharge: float, color: ?StudioPaintColor}
     */
    public function resolveSurcharge(int $studioId, ?int $paintColorId): array
    {
        /** @var Studio $studio */
        $studio = Studio::query()->findOrFail($studioId);

        if ($paintColorId === null) {
            $default = StudioPaintColor::query()
                ->where('studio_id', $studioId)
                ->where('is_default', true)
                ->first();

            return ['surcharge' => 0.0, 'color' => $default];
        }

        /** @var StudioPaintColor $color */
        $color = StudioPaintColor::query()
            ->where('studio_id', $studioId)
            ->findOrFail($paintColorId);

        if ($color->is_default) {
            return ['surcharge' => 0.0, 'color' => $color];
        }

        $mode = (string) ($studio->paint_pricing_mode ?? 'flat_extra');

        if ($mode === 'per_color') {
            if ($color->price === null) {
                throw new BusinessException('Paint color price is not configured.', 'paint_price_missing');
            }

            return ['surcharge' => round((float) $color->price, 2), 'color' => $color];
        }

        $flat = $studio->flat_extra_paint_price;
        if ($flat === null) {
            throw new BusinessException('Flat extra paint price is not configured.', 'paint_flat_price_missing');
        }

        return ['surcharge' => round((float) $flat, 2), 'color' => $color];
    }

    /**
     * @param  array{name: string, hex?: string|null, is_default?: bool, price?: float|null, sort_order?: int}  $data
     */
    public function createColor(int $studioId, array $data): StudioPaintColor
    {
        return DB::transaction(function () use ($studioId, $data) {
            Studio::query()->findOrFail($studioId);

            if (! empty($data['is_default'])) {
                StudioPaintColor::query()->where('studio_id', $studioId)->update(['is_default' => false]);
            }

            return StudioPaintColor::query()->create([
                'studio_id' => $studioId,
                'name' => $data['name'],
                'hex' => $data['hex'] ?? null,
                'is_default' => (bool) ($data['is_default'] ?? false),
                'price' => $data['price'] ?? null,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);
        });
    }

    /**
     * @param  array{name?: string, hex?: string|null, is_default?: bool, price?: float|null, sort_order?: int}  $data
     */
    public function updateColor(int $studioId, int $colorId, array $data): StudioPaintColor
    {
        return DB::transaction(function () use ($studioId, $colorId, $data) {
            /** @var StudioPaintColor $color */
            $color = StudioPaintColor::query()->where('studio_id', $studioId)->findOrFail($colorId);

            if (! empty($data['is_default'])) {
                StudioPaintColor::query()->where('studio_id', $studioId)->where('id', '!=', $colorId)->update(['is_default' => false]);
            }

            $color->update($data);

            return $color->fresh();
        });
    }

    public function deleteColor(int $studioId, int $colorId): void
    {
        StudioPaintColor::query()->where('studio_id', $studioId)->findOrFail($colorId)->delete();
    }

    public function listForStudio(int $studioId)
    {
        return StudioPaintColor::query()
            ->where('studio_id', $studioId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
