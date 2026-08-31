<?php

namespace App\Services\Content;

use App\Exceptions\BusinessException;
use App\Repositories\Contracts\ContentBannerRepositoryInterface;
use App\Repositories\Contracts\ContentPageRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class ContentService
{
    public function __construct(
        protected ContentBannerRepositoryInterface $contentBannerRepository,
        protected ContentPageRepositoryInterface $contentPageRepository,
        protected AppSettingsService $appSettingsService,
    ) {
    }

    public function listBanners(int $perPage = 15): LengthAwarePaginator
    {
        return $this->contentBannerRepository->paginateAdmin($perPage);
    }

    public function listActiveBanners(int $perPage = 15): LengthAwarePaginator
    {
        return $this->contentBannerRepository->paginateActive($perPage);
    }

    public function listPages(int $perPage = 15): LengthAwarePaginator
    {
        return $this->contentPageRepository->paginateAdmin($perPage);
    }

    public function getPageBySlug(string $slug): ?Model
    {
        $page = $this->contentPageRepository->findByKey($slug);

        if ($page && ! $page->is_active) {
            return null;
        }

        return $page;
    }

    public function getPublicSettings(): array
    {
        return $this->appSettingsService->getBookingConfig();
    }

    public function createBanner(array $data): Model
    {
        return $this->contentBannerRepository->create($this->normalizeBannerData($data));
    }

    public function updateBanner(int $id, array $data): Model
    {
        return $this->contentBannerRepository->update($id, $this->normalizeBannerData($data));
    }

    public function deleteBanner(int $id): bool
    {
        return $this->contentBannerRepository->delete($id);
    }

    public function createPage(array $data): Model
    {
        $payload = $this->normalizePageData($data);

        if ($this->contentPageRepository->findByKey($payload['key'])) {
            throw new BusinessException('A page with this key already exists.', 'page_key_exists');
        }

        return $this->contentPageRepository->create($payload);
    }

    public function updatePage(int $id, array $data): Model
    {
        $payload = $this->normalizePageData($data);

        if (isset($payload['key'])) {
            $existing = $this->contentPageRepository->findByKey($payload['key']);

            if ($existing && (int) $existing->id !== $id) {
                throw new BusinessException('A page with this key already exists.', 'page_key_exists');
            }
        }

        return $this->contentPageRepository->update($id, $payload);
    }

    public function deletePage(int $id): bool
    {
        return $this->contentPageRepository->delete($id);
    }

    /**
     * @return array{banners: \Illuminate\Support\Collection, pages: \Illuminate\Support\Collection}
     */
    public function getPublicContent(): array
    {
        return [
            'banners' => $this->contentBannerRepository->getActiveOrdered(),
            'pages' => $this->contentPageRepository->getActive()->keyBy('key'),
        ];
    }

    protected function normalizeBannerData(array $data): array
    {
        $payload = [
            'title' => $data['title'],
            'image_path' => $data['image_path'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
            'link_id' => $data['link_id'] ?? null,
        ];

        if (! empty($data['link_url'])) {
            $payload['link_type'] = $data['link_url'];
        } elseif (isset($data['link_type'])) {
            $payload['link_type'] = $data['link_type'];
        } else {
            $payload['link_type'] = null;
        }

        return $payload;
    }

    protected function normalizePageData(array $data): array
    {
        $payload = $data;

        if (isset($payload['slug'])) {
            $payload['key'] = $payload['slug'];
            unset($payload['slug']);
        }

        return $payload;
    }
}
