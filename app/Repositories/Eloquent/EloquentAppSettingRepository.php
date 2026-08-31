<?php

namespace App\Repositories\Eloquent;

use App\Models\AppSetting;
use App\Repositories\Contracts\AppSettingRepositoryInterface;

class EloquentAppSettingRepository extends BaseRepository implements AppSettingRepositoryInterface
{
    public function __construct(AppSetting $model)
    {
        parent::__construct($model);
    }

    public function findByKey(string $key): ?AppSetting
    {
        return $this->model->newQuery()->where('key', $key)->first();
    }
}
