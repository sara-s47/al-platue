<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\StoreFavoriteRequest;
use App\Http\Resources\Api\V1\FavoriteResource;
use App\Http\Responses\ApiResponse;
use App\Services\Engagement\FavoriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function __construct(protected FavoriteService $favoriteService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->favoriteService->listForUser($request->user()->id, (int) $request->get('per_page', 15));

        return ApiResponse::paginated($paginator->through(fn ($f) => new FavoriteResource($f)));
    }

    public function store(StoreFavoriteRequest $request): JsonResponse
    {
        $favorite = $this->favoriteService->add($request->user()->id, (int) $request->studio_id);

        return ApiResponse::success(new FavoriteResource($favorite), 'Added to favorites.', 201);
    }

    public function destroy(Request $request, int $studioId): JsonResponse
    {
        $this->favoriteService->remove($request->user()->id, $studioId);

        return ApiResponse::success(message: 'Removed from favorites.');
    }
}