<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StudioResource;
use App\Http\Responses\ApiResponse;
use App\Services\Studio\StudioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudioController extends Controller
{
    public function __construct(protected StudioService $studioService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->studioService->listForCustomer($request->only(['category_id', 'search']), (int) $request->get('per_page', 15));

        return ApiResponse::paginated($paginator->through(fn ($s) => new StudioResource($s)));
    }

    public function show(int $id): JsonResponse
    {
        $studio = $this->studioService->find($id)->load(['category', 'images']);

        return ApiResponse::success(new StudioResource($studio));
    }
}