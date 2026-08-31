<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ProcessRefundRequest;
use App\Http\Resources\Api\V1\PaymentResource;
use App\Http\Responses\ApiResponse;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Services\Payment\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPaymentController extends Controller
{
    public function __construct(
        protected PaymentRepositoryInterface $paymentRepository,
        protected RefundService $refundService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $p = $this->paymentRepository->paginate((int) $request->get('per_page', 15));
        return ApiResponse::paginated($p->through(fn ($pay) => new PaymentResource($pay)));
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(new PaymentResource($this->paymentRepository->findOrFail($id)));
    }

    public function refund(ProcessRefundRequest $request, int $paymentId): JsonResponse
    {
        $refund = $this->refundService->processRefund($paymentId, (float) $request->amount, $request->reason, $request->user()->id);
        return ApiResponse::success($refund, 'Refund processed.', 201);
    }
}