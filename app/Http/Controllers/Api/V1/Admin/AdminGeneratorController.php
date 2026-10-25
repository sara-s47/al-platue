<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Support\DayCatalog;

class AdminGeneratorController extends AdminDayCatalogController
{
    protected function catalogType(): string
    {
        return DayCatalog::GENERATOR;
    }
}
