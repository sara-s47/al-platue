<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    public function __construct(protected LoyaltyService $loyaltyService)
    {
    }

    public function balance(Request $request): JsonResponse
    {
        return ApiResponse::success(['balance' => $this->loyaltyService->getBalance($request->user()->id)]);
    }

    public function history(Request $request): JsonResponse
    {
        $paginator = \App\Models\PointsTransaction::query()
            ->where('user_id', $request->user()->id)
            ->latest('created_at')
            ->paginate((int) $request->get('per_page', 15));

        return ApiResponse::paginated($paginator);
    }
}