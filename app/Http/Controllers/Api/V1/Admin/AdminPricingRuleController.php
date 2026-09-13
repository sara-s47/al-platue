<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StorePricingRuleRequest;
use App\Http\Requests\Api\V1\Admin\UpdatePricingRuleRequest;
use App\Http\Resources\Api\V1\PricingRuleResource;
use App\Http\Responses\ApiResponse;
use App\Services\Pricing\PricingRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPricingRuleController extends Controller
{
    public function __construct(protected PricingRuleService $pricingRuleService) {}

    public function index(Request $request): JsonResponse
    {
        $p = $this->pricingRuleService->paginate((int) $request->get('per_page', 15));
        return ApiResponse::paginated($p->through(fn ($r) => new PricingRuleResource($r)));
    }

    public function store(StorePricingRuleRequest $request): JsonResponse
    {
        return ApiResponse::success(new PricingRuleResource($this->pricingRuleService->create($request->validated())), null, 201);
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(new PricingRuleResource($this->pricingRuleService->findOrFail($id)));
    }

    public function update(UpdatePricingRuleRequest $request, int $id): JsonResponse
    {
        return ApiResponse::success(new PricingRuleResource($this->pricingRuleService->update($id, $request->validated())));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->pricingRuleService->delete($id);
        return ApiResponse::success(message: 'Pricing rule deleted.');
    }
}