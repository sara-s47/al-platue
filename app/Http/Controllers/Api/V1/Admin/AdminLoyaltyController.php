<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ManualPointsAdjustRequest;
use App\Http\Requests\Api\V1\Admin\StoreLoyaltyRuleRequest;
use App\Http\Resources\Api\V1\LoyaltyRuleResource;
use App\Http\Responses\ApiResponse;
use App\Services\Loyalty\LoyaltyRuleService;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminLoyaltyController extends Controller
{
    public function __construct(
        protected LoyaltyRuleService $loyaltyRuleService,
        protected LoyaltyService $loyaltyService,
    ) {}

    public function rules(Request $request): JsonResponse
    {
        $p = $this->loyaltyRuleService->paginate((int) $request->get('per_page', 15));
        return ApiResponse::paginated($p->through(fn ($r) => new LoyaltyRuleResource($r)));
    }

    public function storeRule(StoreLoyaltyRuleRequest $request): JsonResponse
    {
        return ApiResponse::success(new LoyaltyRuleResource($this->loyaltyRuleService->create($request->validated())), null, 201);
    }

    public function updateRule(StoreLoyaltyRuleRequest $request, int $id): JsonResponse
    {
        return ApiResponse::success(new LoyaltyRuleResource($this->loyaltyRuleService->update($id, $request->validated())));
    }

    public function destroyRule(int $id): JsonResponse
    {
        $this->loyaltyRuleService->delete($id);
        return ApiResponse::success(message: 'Rule deleted.');
    }

    public function adjust(ManualPointsAdjustRequest $request): JsonResponse
    {
        $tx = $this->loyaltyService->manualAdjust((int) $request->user_id, (float) $request->points, $request->reason, $request->user()->id);
        return ApiResponse::success($tx, 'Points adjusted.', 201);
    }
}