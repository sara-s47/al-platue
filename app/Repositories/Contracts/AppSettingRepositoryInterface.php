<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Model;

interface AppSettingRepositoryInterface extends BaseRepositoryInterface
{
    public function findByKey(string $key): ?Model;
}
