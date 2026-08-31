<?php

namespace App\Repositories\Eloquent;

use App\Models\PricingRule;
use App\Repositories\Contracts\PricingRuleRepositoryInterface;

class EloquentPricingRuleRepository extends BaseRepository implements PricingRuleRepositoryInterface
{
    public function __construct(PricingRule $model)
    {
        parent::__construct($model);
    }
}
