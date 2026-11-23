<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Send appointment reminders every day at 6 PM Damascus time.
Schedule::command('appointments:send-reminders')
    ->dailyAt('18:00')
    ->timezone('Asia/Damascus')
    ->withoutOverlapping();

// Mark appointments as no-show every 15 minutes.
Schedule::command('appointments:mark-no-shows')
    ->everyFifteenMinutes()
    ->timezone('Asia/Damascus')
    ->withoutOverlapping();

// Unblock patients whose booking restriction has expired every hour.
Schedule::command('patients:unblock-expired')
    ->hourly()
    ->timezone('Asia/Damascus')
    ->withoutOverlapping();

// Generate the previous month's branch report on the first day of each month.
Schedule::call(function () {
    Artisan::call('reports:monthly-branches', [
        '--month' => now('Asia/Damascus')->subMonth()->format('Y-m'),
    ]);
})
    ->name('monthly-branch-report')
    ->monthlyOn(1, '00:10')
    ->timezone('Asia/Damascus')
    ->withoutOverlapping();
