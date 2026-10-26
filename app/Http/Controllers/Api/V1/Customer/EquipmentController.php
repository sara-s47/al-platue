<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\EquipmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EquipmentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Equipment;
use App\Repositories\Contracts\EquipmentRepositoryInterface;
use App\Services\Equipment\EquipmentRentalInventoryService;
use App\Services\Equipment\EquipmentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipmentController extends Controller
{
    public function __construct(
        protected EquipmentService $equipmentService,
        protected EquipmentRepositoryInterface $equipmentRepository,
        protected EquipmentRentalInventoryService $rentalInventory,
    ) {
    }

    /**
     * Standalone catalog (no studio). Optional start_date/end_date attach available_quantity.
     */
    public function catalog(Request $request): JsonResponse
    {
        $period = $this->resolveRentalPeriod($request);
        $paginator = $this->equipmentRepository->listForCustomer(
            $request->only(['search', 'category_id']),
            (int) $request->get('per_page', 15),
        );

        if ($period) {
            $paginator->getCollection()->each(fn (Equipment $equipment) => $this->attachAvailability($equipment, $period));
        }

        return ApiResponse::paginated($paginator->through(fn ($e) => new EquipmentResource($e)));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $equipment = Equipment::query()
            ->with(['images', 'category'])
            ->where('status', EquipmentStatus::Active)
            ->findOrFail($id);

        $period = $this->resolveRentalPeriod($request);

        if ($period) {
            $this->attachAvailability($equipment, $period);
        }

        return ApiResponse::success(new EquipmentResource($equipment));
    }

    public function availability(Request $request): JsonResponse
    {
        $period = $this->resolveRentalPeriod($request, required: true);
        $available = $this->rentalInventory->availableQuantities($period[0], $period[1]);

        return ApiResponse::success(collect($available)->map(fn ($quantity, $id) => [
            'equipment_id' => $id,
            'available_quantity' => $quantity,
        ])->values());
    }

    /**
     * Equipment available for a studio booking (existing path).
     */
    public function index(Request $request, int $studioId): JsonResponse
    {
        $request->validate([
            'start_at' => 'nullable|required_with:end_at|date',
            'end_at' => 'nullable|required_with:start_at|date|after:start_at',
        ]);

        $items = $this->equipmentService->getForStudio(
            $studioId,
            $request->filled('start_at') ? Carbon::parse($request->start_at) : null,
            $request->filled('end_at') ? Carbon::parse($request->end_at) : null,
        );

        return ApiResponse::success(EquipmentResource::collection(collect($items)));
    }

    /**
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}|null
     */
    protected function resolveRentalPeriod(Request $request, bool $required = false): ?array
    {
        $rule = $required ? 'required' : 'nullable';
        $data = $request->validate([
            'start_date' => "{$rule}|date_format:Y-m-d|required_with:end_date",
            'end_date' => "{$rule}|date_format:Y-m-d|required_with:start_date|after_or_equal:start_date",
        ]);

        if (empty($data['start_date']) || empty($data['end_date'])) {
            return null;
        }

        return $this->rentalInventory->rentalPeriod($data['start_date'], $data['end_date']);
    }

    /**
     * @param  array{0: \Carbon\Carbon, 1: \Carbon\Carbon}  $period
     */
    protected function attachAvailability(Equipment $equipment, array $period): void
    {
        $allocated = $this->rentalInventory->getAllocatedQuantity($equipment->id, $period[0], $period[1]);
        $equipment->setAttribute('available_quantity', max(0, (int) $equipment->quantity - $allocated));
    }
}
