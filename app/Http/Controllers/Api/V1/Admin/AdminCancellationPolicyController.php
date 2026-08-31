<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreCancellationPolicyRequest;
use App\Http\Resources\Api\V1\PolicyResource;
use App\Http\Responses\ApiResponse;
use App\Services\Booking\PolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCancellationPolicyController extends Controller
{
    public function __construct(protected PolicyService $policyService) {}

    public function index(Request $request): JsonResponse
    {
        $p = $this->policyService->paginateCancellation((int) $request->get('per_page', 15));
        return ApiResponse::paginated($p->through(fn ($pol) => new PolicyResource($pol)));
    }

    public function store(StoreCancellationPolicyRequest $request): JsonResponse
    {
        return ApiResponse::success(new PolicyResource($this->policyService->createCancellation($request->validated())), null, 201);
    }

    public function update(StoreCancellationPolicyRequest $request, int $id): JsonResponse
    {
        return ApiResponse::success(new PolicyResource($this->policyService->updateCancellation($id, $request->validated())));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->policyService->deleteCancellation($id);
        return ApiResponse::success(message: 'Policy deleted.');
    }
}