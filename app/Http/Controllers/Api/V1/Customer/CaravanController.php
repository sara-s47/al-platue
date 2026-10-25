<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Repositories\Contracts\CaravanRepositoryInterface;
use App\Services\DayCatalog\DayCatalogInventoryService;
use App\Support\DayCatalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CaravanController extends DayCatalogController
{
    public function __construct(
        protected CaravanRepositoryInterface $caravanRepository,
        DayCatalogInventoryService $inventory,
    ) {
        parent::__construct($inventory);
    }

    protected function catalogType(): string
    {
        return DayCatalog::CARAVAN;
    }

    protected function listForCustomer(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->caravanRepository->listForCustomer($filters, $perPage);
    }
}
