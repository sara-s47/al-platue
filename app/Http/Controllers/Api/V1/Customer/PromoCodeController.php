<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\ValidatePromoRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Promotion\PromoCodeService;
use Illuminate\Http\JsonResponse;

class PromoCodeController extends Controller
{
    public function __construct(protected PromoCodeService $promoCodeService)
    {
    }

    public function validate(ValidatePromoRequest $request): JsonResponse
    {
        $result = $this->promoCodeService->validate(
            $request->code,
            $request->user()->id,
            (int) $request->studio_id,
            (float) $request->subtotal,
        );

        return ApiResponse::success($result);
    }
}