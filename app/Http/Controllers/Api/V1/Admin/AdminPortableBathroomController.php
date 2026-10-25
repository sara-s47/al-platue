<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Support\DayCatalog;

class AdminPortableBathroomController extends AdminDayCatalogController
{
    protected function catalogType(): string
    {
        return DayCatalog::PORTABLE_BATHROOM;
    }
}
