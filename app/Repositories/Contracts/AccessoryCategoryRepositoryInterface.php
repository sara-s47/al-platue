<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface AccessoryCategoryRepositoryInterface extends BaseRepositoryInterface
{
    public function allOrdered(): Collection;
}
