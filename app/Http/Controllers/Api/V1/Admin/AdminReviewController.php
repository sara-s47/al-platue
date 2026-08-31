<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ModerateReviewRequest;
use App\Http\Resources\Api\V1\ReviewResource;
use App\Http\Responses\ApiResponse;
use App\Services\Review\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReviewController extends Controller
{
    public function __construct(protected ReviewService $reviewService) {}

    public function index(Request $request): JsonResponse
    {
        $p = $this->reviewService->paginate((int) $request->get('per_page', 15));
        return ApiResponse::paginated($p->through(fn ($r) => new ReviewResource($r)));
    }

    public function moderate(ModerateReviewRequest $request, int $id): JsonResponse
    {
        return ApiResponse::success(new ReviewResource($this->reviewService->moderate($id, $request->status)));
    }

    public function dimensions(Request $request): JsonResponse
    {
        return ApiResponse::paginated($this->reviewService->listDimensions((int) $request->get('per_page', 15)));
    }

    public function storeDimension(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:255', 'is_active' => 'boolean']);
        return ApiResponse::success($this->reviewService->createDimension($request->all()), null, 201);
    }
}