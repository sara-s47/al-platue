<?php

namespace App\Services\Content;

use App\Enums\CatalogSection;
use App\Exceptions\BusinessException;
use App\Models\CatalogSectionCover;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class CatalogSectionCoverService
{
    public function all(): Collection
    {
        $this->ensureRowsExist();

        return CatalogSectionCover::query()
            ->orderBy('id')
            ->get();
    }

    public function findBySection(string $section): CatalogSectionCover
    {
        $this->ensureRowsExist();

        $cover = CatalogSectionCover::query()->where('section', $section)->first();

        if (! $cover) {
            throw new BusinessException('Unknown catalog section.', 'invalid_catalog_section', 404);
        }

        return $cover;
    }

    public function updateCover(string $section, ?string $coverPath, bool $clear = false): CatalogSectionCover
    {
        $cover = $this->findBySection($section);

        if ($clear) {
            $this->deleteFile($cover->cover_image);
            $cover->update(['cover_image' => null]);

            return $cover->fresh();
        }

        if ($coverPath !== null) {
            $this->deleteFile($cover->cover_image);
            $cover->update(['cover_image' => $coverPath]);
        }

        return $cover->fresh();
    }

    protected function ensureRowsExist(): void
    {
        $existing = CatalogSectionCover::query()->pluck('section')
            ->map(fn ($section) => $section instanceof CatalogSection ? $section->value : (string) $section)
            ->all();

        foreach (CatalogSection::cases() as $section) {
            if (! in_array($section->value, $existing, true)) {
                CatalogSectionCover::query()->create([
                    'section' => $section->value,
                    'cover_image' => null,
                ]);
            }
        }
    }

    protected function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
