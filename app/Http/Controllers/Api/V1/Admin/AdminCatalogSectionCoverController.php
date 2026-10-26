<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateCatalogSectionCoverRequest;
use App\Http\Resources\Api\V1\CatalogSectionCoverResource;
use App\Http\Responses\ApiResponse;
use App\Services\Content\CatalogSectionCoverService;
use Illuminate\Http\JsonResponse;

class AdminCatalogSectionCoverController extends Controller
{
    public function __construct(protected CatalogSectionCoverService $coverService) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success(
            CatalogSectionCoverResource::collection($this->coverService->all()),
        );
    }

    public function update(UpdateCatalogSectionCoverRequest $request): JsonResponse
    {
        $section = $request->validated('section');
        $clear = $request->boolean('clear_cover');
        $path = null;

        if (! $clear && $request->hasFile('cover_image')) {
            $path = $request->file('cover_image')->store('catalog-section-covers', 'public');
        }

        return ApiResponse::success(
            new CatalogSectionCoverResource(
                $this->coverService->updateCover($section, $path, $clear),
            ),
            'Catalog section cover updated.',
        );
    }
}
