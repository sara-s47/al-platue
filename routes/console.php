<?php

use App\Jobs\ExpireBookingHoldsJob;
use App\Jobs\ExpireLoyaltyPointsJob;
use App\Jobs\ProcessScheduledNotificationsJob;
use App\Jobs\SendBookingRemindersJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::job(new ExpireBookingHoldsJob)->everyMinute();
Schedule::job(new SendBookingRemindersJob)->hourly();
Schedule::job(new ProcessScheduledNotificationsJob)->everyMinute();
Schedule::job(new ExpireLoyaltyPointsJob)->daily();
