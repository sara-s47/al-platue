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
        $userId = $request->user()->id;
        $options = $this->loyaltyService->getRedemptionOptions($userId);

        return ApiResponse::success([
            'balance' => $this->loyaltyService->getBalance($userId),
            'redemption' => [
                'enabled' => $options['enabled'],
                'can_redeem' => $options['can_redeem'],
                'points_per_currency_unit' => $options['points_per_currency_unit'],
                'min_redemption_points' => $options['min_redemption_points'],
                'max_redemption_amount' => $options['max_redemption_amount'],
                'max_redemption_percentage' => $options['max_redemption_percentage'],
                'balance_value' => $options['max_discount'],
            ],
        ]);
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