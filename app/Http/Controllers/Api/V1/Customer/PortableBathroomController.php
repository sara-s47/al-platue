<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Repositories\Contracts\PortableBathroomRepositoryInterface;
use App\Services\DayCatalog\DayCatalogInventoryService;
use App\Support\DayCatalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PortableBathroomController extends DayCatalogController
{
    public function __construct(
        protected PortableBathroomRepositoryInterface $portableBathroomRepository,
        DayCatalogInventoryService $inventory,
    ) {
        parent::__construct($inventory);
    }

    protected function catalogType(): string
    {
        return DayCatalog::PORTABLE_BATHROOM;
    }

    protected function listForCustomer(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->portableBathroomRepository->listForCustomer($filters, $perPage);
    }
}
