<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymobWebhookController extends Controller
{
    public function __construct(protected PaymentService $paymentService)
    {
    }

    public function handle(Request $request): JsonResponse
    {
        $hmac = $request->header('HMAC') ?? $request->input('hmac', '');
        $result = $this->paymentService->handleWebhook($request->all(), (string) $hmac);

        return ApiResponse::success($result);
    }
}