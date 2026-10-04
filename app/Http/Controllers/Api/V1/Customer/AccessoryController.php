<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AccessoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Accessory;
use App\Repositories\Contracts\AccessoryRepositoryInterface;
use App\Services\Accessory\AccessoryInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccessoryController extends Controller
{
    public function __construct(
        protected AccessoryRepositoryInterface $accessoryRepository,
        protected AccessoryInventoryService $inventory,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $period = $this->resolvePeriod($request);
        $paginator = $this->accessoryRepository->listForCustomer(
            $request->only(['search']),
            (int) $request->get('per_page', 15),
        );

        if ($period) {
            $paginator->getCollection()->each(fn (Accessory $accessory) => $this->attachAvailability($accessory, $period));
        }

        return ApiResponse::paginated($paginator->through(fn ($a) => new AccessoryResource($a)));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $accessory = Accessory::query()->with('images')->where('is_active', true)->findOrFail($id);
        $period = $this->resolvePeriod($request);

        if ($period) {
            $this->attachAvailability($accessory, $period);
        }

        return ApiResponse::success(new AccessoryResource($accessory));
    }

    public function availability(Request $request): JsonResponse
    {
        $period = $this->resolvePeriod($request, required: true);
        $available = $this->inventory->availableQuantities($period[0], $period[1]);

        return ApiResponse::success(collect($available)->map(fn ($quantity, $id) => [
            'accessory_id' => $id,
            'available_quantity' => $quantity,
        ])->values());
    }

    /**
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}|null
     */
    protected function resolvePeriod(Request $request, bool $required = false): ?array
    {
        $rule = $required ? 'required' : 'nullable';
        $data = $request->validate([
            'start_date' => "{$rule}|date_format:Y-m-d|required_with:end_date",
            'end_date' => "{$rule}|date_format:Y-m-d|required_with:start_date|after_or_equal:start_date",
        ]);

        if (empty($data['start_date']) || empty($data['end_date'])) {
            return null;
        }

        return $this->inventory->rentalPeriod($data['start_date'], $data['end_date']);
    }

    /**
     * @param  array{0: \Carbon\Carbon, 1: \Carbon\Carbon}  $period
     */
    protected function attachAvailability(Accessory $accessory, array $period): void
    {
        $allocated = $this->inventory->getAllocatedQuantity($accessory->id, $period[0], $period[1]);
        $accessory->setAttribute('available_quantity', max(0, (int) $accessory->quantity - $allocated));
    }
}
