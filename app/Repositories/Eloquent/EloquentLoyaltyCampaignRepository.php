<?php

namespace App\Repositories\Eloquent;

use App\Models\LoyaltyCampaign;
use App\Repositories\Contracts\LoyaltyCampaignRepositoryInterface;

class EloquentLoyaltyCampaignRepository extends BaseRepository implements LoyaltyCampaignRepositoryInterface
{
    public function __construct(LoyaltyCampaign $model)
    {
        parent::__construct($model);
    }
}
