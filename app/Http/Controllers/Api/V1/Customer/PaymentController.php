<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\PaymentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\InitiatePaymentRequest;
use App\Http\Resources\Api\V1\PaymentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Booking;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(protected PaymentService $paymentService)
    {
    }

    public function initiate(InitiatePaymentRequest $request, int $bookingId): JsonResponse
    {
        Booking::query()->where('user_id', $request->user()->id)->findOrFail($bookingId);
        $result = $this->paymentService->initiate(
            $bookingId,
            PaymentType::from($request->type),
            $request->amount,
            $request->only(['billing_data']) ?: [],
        );

        return ApiResponse::success($result, 'Payment initiated.', 201);
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(new PaymentResource($this->paymentService->getPaymentStatus($id)));
    }
}