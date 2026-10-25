<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Support\DayCatalog;

class AdminCaravanController extends AdminDayCatalogController
{
    protected function catalogType(): string
    {
        return DayCatalog::CARAVAN;
    }
}
