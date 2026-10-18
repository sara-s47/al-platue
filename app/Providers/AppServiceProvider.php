<?php

namespace App\Providers;

use App\Models\Accessory;
use App\Models\Equipment;
use App\Models\Studio;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'studio' => Studio::class,
            'equipment' => Equipment::class,
            'accessory' => Accessory::class,
        ]);
    }
}
