<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface CategoryRepositoryInterface extends BaseRepositoryInterface
{
    public function allOrdered(): Collection;
}
