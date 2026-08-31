<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StorePromoCodeRequest;
use App\Http\Resources\Api\V1\PromoCodeResource;
use App\Http\Responses\ApiResponse;
use App\Services\Promotion\PromoCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPromoCodeController extends Controller
{
    public function __construct(protected PromoCodeService $promoCodeService) {}

    public function index(Request $request): JsonResponse
    {
        $p = $this->promoCodeService->paginate((int) $request->get('per_page', 15));
        return ApiResponse::paginated($p->through(fn ($pc) => new PromoCodeResource($pc)));
    }

    public function store(StorePromoCodeRequest $request): JsonResponse
    {
        $d = $request->validated();
        $promo = $this->promoCodeService->create($d, $d['studio_ids'] ?? []);
        return ApiResponse::success(new PromoCodeResource($promo), null, 201);
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(new PromoCodeResource($this->promoCodeService->findOrFail($id)));
    }

    public function update(StorePromoCodeRequest $request, int $id): JsonResponse
    {
        $d = $request->validated();
        $promo = $this->promoCodeService->update($id, $d, $d['studio_ids'] ?? null);
        return ApiResponse::success(new PromoCodeResource($promo));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->promoCodeService->delete($id);
        return ApiResponse::success(message: 'Promo code deleted.');
    }
}