<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface InteriorDesignerRepositoryInterface extends BaseRepositoryInterface
{
    public function listActive(): Collection;
}
