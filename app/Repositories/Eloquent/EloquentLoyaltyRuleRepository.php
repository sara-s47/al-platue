<?php

namespace App\Repositories\Eloquent;

use App\Models\LoyaltyRule;
use App\Repositories\Contracts\LoyaltyRuleRepositoryInterface;

class EloquentLoyaltyRuleRepository extends BaseRepository implements LoyaltyRuleRepositoryInterface
{
    public function __construct(LoyaltyRule $model)
    {
        parent::__construct($model);
    }
}
