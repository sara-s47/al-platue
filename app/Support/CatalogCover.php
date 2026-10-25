<?php

namespace App\Support;

use Illuminate\Support\Collection;

class CatalogCover
{
    /**
     * Prefer dedicated cover_image; otherwise first gallery image.
     *
     * @param  Collection<int, object>|iterable<int, object>|null  $images
     */
    public static function url(?string $coverPath, $images = null, bool $sortBySortOrder = false): ?string
    {
        if ($coverPath !== null && trim($coverPath) !== '') {
            return PublicStorageUrl::from($coverPath);
        }

        if ($images === null) {
            return null;
        }

        $collection = $images instanceof Collection ? $images : collect($images);

        if ($collection->isEmpty()) {
            return null;
        }

        $first = $sortBySortOrder
            ? $collection->sortBy('sort_order')->first()
            : $collection->first();

        if ($first === null) {
            return null;
        }

        if (isset($first->public_path) && $first->public_path) {
            return $first->public_path;
        }

        return PublicStorageUrl::from($first->path ?? null);
    }
}
