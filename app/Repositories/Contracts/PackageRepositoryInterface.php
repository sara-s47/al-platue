<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface PackageRepositoryInterface extends BaseRepositoryInterface
{
    public function listForStudio(int $studioId): Collection;
}
