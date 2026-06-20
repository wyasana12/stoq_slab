<?php

use App\Jobs\GenerateDssCacheJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:send-warehouse')->dailyAt('08:00')->timezone('Asia/Jakarta');

Schedule::job(new GenerateDssCacheJob())->dailyAt('08:00')->timezone('Asia/Jakarta');