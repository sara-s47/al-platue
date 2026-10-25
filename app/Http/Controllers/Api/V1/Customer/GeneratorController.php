<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Repositories\Contracts\GeneratorRepositoryInterface;
use App\Services\DayCatalog\DayCatalogInventoryService;
use App\Support\DayCatalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GeneratorController extends DayCatalogController
{
    public function __construct(
        protected GeneratorRepositoryInterface $generatorRepository,
        DayCatalogInventoryService $inventory,
    ) {
        parent::__construct($inventory);
    }

    protected function catalogType(): string
    {
        return DayCatalog::GENERATOR;
    }

    protected function listForCustomer(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->generatorRepository->listForCustomer($filters, $perPage);
    }
}
