<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\StoreFavoriteRequest;
use App\Http\Resources\Api\V1\FavoriteResource;
use App\Http\Responses\ApiResponse;
use App\Models\Favorite;
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
        $type = $request->query('type');
        if ($type !== null && ! in_array($type, Favorite::TYPES, true)) {
            $type = null;
        }

        $paginator = $this->favoriteService->listForUser(
            $request->user()->id,
            (int) $request->get('per_page', 15),
            $type,
        );

        return ApiResponse::paginated($paginator->through(fn ($f) => new FavoriteResource($f)));
    }

    public function store(StoreFavoriteRequest $request): JsonResponse
    {
        $favorite = $this->favoriteService->add(
            $request->user()->id,
            (string) $request->validated('type'),
            (int) $request->validated('id'),
        );

        return ApiResponse::success(new FavoriteResource($favorite), 'Added to favorites.', 201);
    }

    public function destroy(Request $request, string $type, int $id): JsonResponse
    {
        $this->favoriteService->remove($request->user()->id, $type, $id);

        return ApiResponse::success(message: 'Removed from favorites.');
    }

    /**
     * Backward-compatible delete: DELETE /favorites/{studioId}
     */
    public function destroyStudio(Request $request, int $studioId): JsonResponse
    {
        $this->favoriteService->remove($request->user()->id, Favorite::TYPE_STUDIO, $studioId);

        return ApiResponse::success(message: 'Removed from favorites.');
    }
}
