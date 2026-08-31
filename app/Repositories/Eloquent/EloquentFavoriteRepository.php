<?php

namespace App\Repositories\Eloquent;

use App\Models\Favorite;
use App\Repositories\Contracts\FavoriteRepositoryInterface;

class EloquentFavoriteRepository extends BaseRepository implements FavoriteRepositoryInterface
{
    public function __construct(Favorite $model)
    {
        parent::__construct($model);
    }
}
