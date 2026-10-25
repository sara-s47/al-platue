<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DayCatalogProductResource;
use App\Http\Responses\ApiResponse;
use App\Services\DayCatalog\DayCatalogInventoryService;
use App\Support\DayCatalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class DayCatalogController extends Controller
{
    public function __construct(
        protected DayCatalogInventoryService $inventory,
    ) {
    }

    abstract protected function catalogType(): string;

    abstract protected function listForCustomer(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function index(Request $request): JsonResponse
    {
        $period = $this->resolvePeriod($request);
        $paginator = $this->listForCustomer(
            $request->only(['search']),
            (int) $request->get('per_page', 15),
        );

        if ($period) {
            $paginator->getCollection()->each(
                fn (Model $product) => $this->attachAvailability($product, $period),
            );
        }

        return ApiResponse::paginated($paginator->through(fn ($item) => new DayCatalogProductResource($item)));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $modelClass = DayCatalog::modelClass($this->catalogType());
        $product = $modelClass::query()->with('images')->where('is_active', true)->findOrFail($id);
        $period = $this->resolvePeriod($request);

        if ($period) {
            $this->attachAvailability($product, $period);
        }

        return ApiResponse::success(new DayCatalogProductResource($product));
    }

    public function availability(Request $request): JsonResponse
    {
        $period = $this->resolvePeriod($request, required: true);
        $available = $this->inventory->availableQuantities($this->catalogType(), $period[0], $period[1]);
        $fk = DayCatalog::productFk($this->catalogType());

        return ApiResponse::success(collect($available)->map(fn ($quantity, $id) => [
            $fk => $id,
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
    protected function attachAvailability(Model $product, array $period): void
    {
        $allocated = $this->inventory->getAllocatedQuantity(
            $this->catalogType(),
            $product->id,
            $period[0],
            $period[1],
        );
        $product->setAttribute('available_quantity', max(0, (int) $product->quantity - $allocated));
    }
}
