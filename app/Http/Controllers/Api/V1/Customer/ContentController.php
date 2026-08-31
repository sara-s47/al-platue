<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ContentBannerResource;
use App\Http\Resources\Api\V1\ContentPageResource;
use App\Http\Responses\ApiResponse;
use App\Services\Content\ContentService;
use Illuminate\Http\JsonResponse;

class ContentController extends Controller
{
    public function __construct(protected ContentService $contentService)
    {
    }

    public function banners(): JsonResponse
    {
        $paginator = $this->contentService->listActiveBanners();

        return ApiResponse::paginated($paginator->through(fn ($b) => new ContentBannerResource($b)));
    }

    public function page(string $slug): JsonResponse
    {
        $page = $this->contentService->getPageBySlug($slug);
        abort_if(! $page, 404);

        return ApiResponse::success(new ContentPageResource($page));
    }

    public function settings(): JsonResponse
    {
        return ApiResponse::success($this->contentService->getPublicSettings());
    }
}