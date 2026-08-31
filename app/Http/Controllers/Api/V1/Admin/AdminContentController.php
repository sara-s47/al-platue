<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreContentBannerRequest;
use App\Http\Requests\Api\V1\Admin\StoreContentPageRequest;
use App\Http\Resources\Api\V1\ContentBannerResource;
use App\Http\Resources\Api\V1\ContentPageResource;
use App\Http\Responses\ApiResponse;
use App\Services\Content\ContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminContentController extends Controller
{
    public function __construct(protected ContentService $contentService) {}

    public function banners(Request $request): JsonResponse
    {
        $p = $this->contentService->listBanners((int) $request->get('per_page', 15));
        return ApiResponse::paginated($p->through(fn ($b) => new ContentBannerResource($b)));
    }

    public function storeBanner(StoreContentBannerRequest $request): JsonResponse
    {
        return ApiResponse::success(new ContentBannerResource($this->contentService->createBanner($request->validated())), null, 201);
    }

    public function updateBanner(StoreContentBannerRequest $request, int $id): JsonResponse
    {
        return ApiResponse::success(new ContentBannerResource($this->contentService->updateBanner($id, $request->validated())));
    }

    public function destroyBanner(int $id): JsonResponse
    {
        $this->contentService->deleteBanner($id);
        return ApiResponse::success(message: 'Banner deleted.');
    }

    public function pages(Request $request): JsonResponse
    {
        $p = $this->contentService->listPages((int) $request->get('per_page', 15));
        return ApiResponse::paginated($p->through(fn ($pg) => new ContentPageResource($pg)));
    }

    public function storePage(StoreContentPageRequest $request): JsonResponse
    {
        return ApiResponse::success(new ContentPageResource($this->contentService->createPage($request->validated())), null, 201);
    }

    public function updatePage(StoreContentPageRequest $request, int $id): JsonResponse
    {
        return ApiResponse::success(new ContentPageResource($this->contentService->updatePage($id, $request->validated())));
    }

    public function destroyPage(int $id): JsonResponse
    {
        $this->contentService->deletePage($id);
        return ApiResponse::success(message: 'Page deleted.');
    }
}