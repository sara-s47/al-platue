<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface ContentPageRepositoryInterface extends BaseRepositoryInterface
{
    public function findByKey(string $key): ?Model;

    public function getActive(): Collection;
}
