<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\CreateEquipmentRentalRequest;
use App\Http\Requests\Api\V1\Customer\EquipmentRentalQuoteRequest;
use App\Http\Resources\Api\V1\RentalOrderResource;
use App\Http\Responses\ApiResponse;
use App\Services\Rental\RentalOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Legacy equipment-only routes — create/list against unified rental_orders.
 * Prefer POST /cart/checkout and /rental-orders for new clients.
 */
class EquipmentRentalController extends Controller
{
    public function __construct(protected RentalOrderService $orderService) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->orderService->paginateForUser($request->user()->id, (int) $request->get('per_page', 15));

        return ApiResponse::paginated($paginator->through(fn ($o) => new RentalOrderResource($o)));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return ApiResponse::success(new RentalOrderResource(
            $this->orderService->findForUser($id, $request->user()->id),
        ));
    }

    public function quote(EquipmentRentalQuoteRequest $request): JsonResponse
    {
        $data = $request->validated();
        $items = array_map(fn ($item) => [
            'type' => 'equipment',
            'id' => (int) $item['id'],
            'quantity' => (int) $item['quantity'],
        ], $data['items']);

        return ApiResponse::success($this->orderService->quote($data['start_date'], $data['end_date'], $items));
    }

    public function store(CreateEquipmentRentalRequest $request): JsonResponse
    {
        $data = $request->validated();
        $items = array_map(fn ($item) => [
            'type' => 'equipment',
            'id' => (int) $item['id'],
            'quantity' => (int) $item['quantity'],
        ], $data['items']);

        $order = $this->orderService->create($request->user()->id, [
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'customer_notes' => $data['customer_notes'] ?? null,
            'items' => $items,
        ]);

        return ApiResponse::success(new RentalOrderResource($order), 'Order created.', 201);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $order = $this->orderService->cancelByCustomer($id, $request->user()->id);

        return ApiResponse::success(new RentalOrderResource($order), 'Order cancelled.');
    }
}
