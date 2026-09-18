<?php

use App\Console\Commands\BackupDatabase;
use App\Console\Commands\ExpireTenantSubscriptions;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(ExpireTenantSubscriptions::class)->dailyAt('01:15');
Schedule::command(BackupDatabase::class)->dailyAt('02:30');
