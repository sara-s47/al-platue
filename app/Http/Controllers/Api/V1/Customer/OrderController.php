<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\RentalOrderQuoteRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Http\Responses\ApiResponse;
use App\Services\Order\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(protected OrderService $orderService) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->orderService->paginateForUser($request->user()->id, (int) $request->get('per_page', 15));

        return ApiResponse::paginated($paginator->through(fn ($o) => new OrderResource($o)));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return ApiResponse::success(new OrderResource(
            $this->orderService->findForUser($id, $request->user()->id),
        ));
    }

    public function quote(RentalOrderQuoteRequest $request): JsonResponse
    {
        $data = $request->validated();
        $fromCart = (bool) ($data['from_cart'] ?? false);

        return ApiResponse::success($this->orderService->quote(
            $data['start_date'] ?? null,
            $data['end_date'] ?? null,
            $fromCart ? null : ($data['items'] ?? []),
            $request->user()->id,
        ));
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $order = $this->orderService->cancelByCustomer($id, $request->user()->id);

        return ApiResponse::success(new OrderResource($order), 'Order cancelled.');
    }
}
