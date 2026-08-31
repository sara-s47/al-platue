<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface ContentBannerRepositoryInterface extends BaseRepositoryInterface
{
    public function getActiveOrdered(): Collection;
}
