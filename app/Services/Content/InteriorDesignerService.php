<?php

namespace App\Services\Content;

use App\Models\InteriorDesigner;
use App\Repositories\Contracts\InteriorDesignerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class InteriorDesignerService
{
    public function __construct(
        protected InteriorDesignerRepositoryInterface $designerRepository,
    ) {
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->designerRepository->paginate($perPage);
    }

    public function listActive(): Collection
    {
        return $this->designerRepository->listActive();
    }

    public function find(int $id): InteriorDesigner
    {
        return $this->designerRepository->findOrFail($id);
    }

    public function findActive(int $id): InteriorDesigner
    {
        return InteriorDesigner::query()->where('is_active', true)->findOrFail($id);
    }

    public function create(array $data, ?string $imagePath = null): InteriorDesigner
    {
        $data['notes'] = $this->normalizeNotes($data['notes'] ?? null);
        $data['image_path'] = $imagePath;

        return $this->designerRepository->create($data)->fresh();
    }

    /**
     * @param  string|false|null  $imagePath  string replaces the image, false removes it, null keeps it.
     */
    public function update(int $id, array $data, string|false|null $imagePath = null): InteriorDesigner
    {
        $designer = $this->find($id);

        if (array_key_exists('notes', $data)) {
            $data['notes'] = $this->normalizeNotes($data['notes']);
        }

        if ($imagePath !== null) {
            $this->deleteImage($designer->image_path);
            $data['image_path'] = $imagePath ?: null;
        }

        return $this->designerRepository->update($id, $data);
    }

    public function delete(int $id): bool
    {
        $designer = $this->find($id);
        $this->deleteImage($designer->image_path);

        return $this->designerRepository->delete($id);
    }

    /**
     * @return list<string>|null
     */
    protected function normalizeNotes(mixed $notes): ?array
    {
        if (! is_array($notes)) {
            return null;
        }

        $clean = array_values(array_filter(
            array_map(fn ($note) => trim((string) $note), $notes),
            fn (string $note) => $note !== '',
        ));

        return $clean === [] ? null : $clean;
    }

    protected function deleteImage(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
