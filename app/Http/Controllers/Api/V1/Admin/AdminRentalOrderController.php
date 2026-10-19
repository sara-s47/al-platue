<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\RentalOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateRentalOrderStatusRequest;
use App\Http\Resources\Api\V1\RentalOrderResource;
use App\Http\Responses\ApiResponse;
use App\Services\Rental\RentalOrderService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRentalOrderController extends Controller
{
    public function __construct(protected RentalOrderService $orderService) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => 'nullable|string|max:20',
            'payment_method' => 'nullable|in:online,cash',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'search' => 'nullable|string|max:100',
        ]);

        if (! empty($filters['from'])) {
            $filters['from'] = Carbon::parse($filters['from'])->toDateString();
        }
        if (! empty($filters['to'])) {
            $filters['to'] = Carbon::parse($filters['to'])->toDateString();
        }

        $p = $this->orderService->paginateForAdmin($filters, (int) $request->get('per_page', 15));

        return ApiResponse::paginated($p->through(fn ($o) => new RentalOrderResource($o)));
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(new RentalOrderResource($this->orderService->findForAdmin($id)));
    }

    public function updateStatus(UpdateRentalOrderStatusRequest $request, int $id): JsonResponse
    {
        $order = $this->orderService->updateStatus($id, RentalOrderStatus::from($request->status));

        return ApiResponse::success(new RentalOrderResource($order), 'Order status updated.');
    }

    public function markPaid(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'nullable|numeric|min:0.01',
        ]);

        $order = $this->orderService->markCashPaid($id, isset($data['amount']) ? (float) $data['amount'] : null);

        return ApiResponse::success(new RentalOrderResource($order), 'Cash payment recorded.');
    }
}
