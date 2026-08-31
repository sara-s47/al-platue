<?php

namespace App\Services\Content;

use App\Exceptions\BusinessException;
use App\Repositories\Contracts\ContentBannerRepositoryInterface;
use App\Repositories\Contracts\ContentPageRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class ContentService
{
    public function __construct(
        protected ContentBannerRepositoryInterface $contentBannerRepository,
        protected ContentPageRepositoryInterface $contentPageRepository,
    ) {
    }

    public function createBanner(array $data): Model
    {
        return $this->contentBannerRepository->create($data);
    }

    public function updateBanner(int $id, array $data): Model
    {
        return $this->contentBannerRepository->update($id, $data);
    }

    public function deleteBanner(int $id): bool
    {
        return $this->contentBannerRepository->delete($id);
    }

    public function createPage(array $data): Model
    {
        if ($this->contentPageRepository->findByKey($data['key'])) {
            throw new BusinessException('A page with this key already exists.', 'page_key_exists');
        }

        return $this->contentPageRepository->create($data);
    }

    public function updatePage(int $id, array $data): Model
    {
        if (isset($data['key'])) {
            $existing = $this->contentPageRepository->findByKey($data['key']);

            if ($existing && (int) $existing->id !== $id) {
                throw new BusinessException('A page with this key already exists.', 'page_key_exists');
            }
        }

        return $this->contentPageRepository->update($id, $data);
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
}
