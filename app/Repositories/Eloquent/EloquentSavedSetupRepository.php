<?php

namespace App\Repositories\Eloquent;

use App\Models\SavedSetup;
use App\Repositories\Contracts\SavedSetupRepositoryInterface;

class EloquentSavedSetupRepository extends BaseRepository implements SavedSetupRepositoryInterface
{
    public function __construct(SavedSetup $model)
    {
        parent::__construct($model);
    }
}
