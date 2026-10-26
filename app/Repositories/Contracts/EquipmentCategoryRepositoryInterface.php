<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface EquipmentCategoryRepositoryInterface extends BaseRepositoryInterface
{
    public function allOrdered(): Collection;
}
