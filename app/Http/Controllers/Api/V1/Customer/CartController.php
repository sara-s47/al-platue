<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\CheckoutCartRequest;
use App\Http\Requests\Api\V1\Customer\StoreCartItemRequest;
use App\Http\Requests\Api\V1\Customer\UpdateCartItemRequest;
use App\Http\Resources\Api\V1\CartItemResource;
use App\Http\Resources\Api\V1\RentalOrderResource;
use App\Http\Responses\ApiResponse;
use App\Services\Engagement\CartService;
use App\Services\Rental\RentalOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected RentalOrderService $orderService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $items = $this->cartService->listForUser($request->user()->id);

        return ApiResponse::success(CartItemResource::collection($items));
    }

    public function store(StoreCartItemRequest $request): JsonResponse
    {
        $item = $this->cartService->addOrUpdate(
            $request->user()->id,
            (string) $request->validated('type'),
            (int) $request->validated('id'),
            (int) $request->validated('quantity'),
        );

        return ApiResponse::success(new CartItemResource($item), 'Added to cart.', 201);
    }

    public function update(UpdateCartItemRequest $request, string $type, int $id): JsonResponse
    {
        $item = $this->cartService->setQuantity(
            $request->user()->id,
            $type,
            $id,
            (int) $request->validated('quantity'),
        );

        return ApiResponse::success(new CartItemResource($item), 'Cart updated.');
    }

    public function destroy(Request $request, string $type, int $id): JsonResponse
    {
        $this->cartService->remove($request->user()->id, $type, $id);

        return ApiResponse::success(message: 'Removed from cart.');
    }

    public function clear(Request $request): JsonResponse
    {
        $this->cartService->clear($request->user()->id);

        return ApiResponse::success(message: 'Cart cleared.');
    }

    public function checkout(CheckoutCartRequest $request): JsonResponse
    {
        $order = $this->orderService->checkoutFromCart($request->user()->id, $request->validated());

        return ApiResponse::success(new RentalOrderResource($order), 'Order created.', 201);
    }
}
