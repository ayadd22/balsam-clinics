<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

// Use database cache locally and Redis cache on production.
Schedule::useCache(env('SCHEDULE_CACHE_STORE', 'database'));

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 1. Appointment reminders.
Schedule::command('appointments:send-reminders')
    ->dailyAt('18:00')
    ->timezone('Asia/Damascus')
    ->name('send-appointment-reminders')
    ->withoutOverlapping()
    ->onOneServer()
    ->before(function () {
        Log::channel('schedule')->info('Scheduled task started.', [
            'task' => 'appointments:send-reminders',
            'started_at' => now()->toIso8601String(),
        ]);
    })
    ->onSuccess(function () {
        Log::channel('schedule')->info('Scheduled task completed.', [
            'task' => 'appointments:send-reminders',
            'completed_at' => now()->toIso8601String(),
            'status' => 'success',
        ]);
    })
    ->onFailure(function () {
        Log::channel('schedule')->error('Scheduled task failed.', [
            'task' => 'appointments:send-reminders',
            'failed_at' => now()->toIso8601String(),
            'status' => 'failed',
        ]);
    });

// 2. Mark no-show appointments.
Schedule::command('appointments:mark-no-shows')
    ->everyFifteenMinutes()
    ->timezone('Asia/Damascus')
    ->name('mark-no-show-appointments')
    ->withoutOverlapping()
    ->onOneServer()
    ->before(function () {
        Log::channel('schedule')->info('Scheduled task started.', [
            'task' => 'appointments:mark-no-shows',
            'started_at' => now()->toIso8601String(),
        ]);
    })
    ->onSuccess(function () {
        Log::channel('schedule')->info('Scheduled task completed.', [
            'task' => 'appointments:mark-no-shows',
            'completed_at' => now()->toIso8601String(),
            'status' => 'success',
        ]);
    })
    ->onFailure(function () {
        Log::channel('schedule')->error('Scheduled task failed.', [
            'task' => 'appointments:mark-no-shows',
            'failed_at' => now()->toIso8601String(),
            'status' => 'failed',
        ]);
    });

// 3. Unblock expired patients.
Schedule::command('patients:unblock-expired')
    ->hourly()
    ->timezone('Asia/Damascus')
    ->name('unblock-expired-patients')
    ->withoutOverlapping()
    ->onOneServer()
    ->before(function () {
        Log::channel('schedule')->info('Scheduled task started.', [
            'task' => 'patients:unblock-expired',
            'started_at' => now()->toIso8601String(),
        ]);
    })
    ->onSuccess(function () {
        Log::channel('schedule')->info('Scheduled task completed.', [
            'task' => 'patients:unblock-expired',
            'completed_at' => now()->toIso8601String(),
            'status' => 'success',
        ]);
    })
    ->onFailure(function () {
        Log::channel('schedule')->error('Scheduled task failed.', [
            'task' => 'patients:unblock-expired',
            'failed_at' => now()->toIso8601String(),
            'status' => 'failed',
        ]);
    });

// 4. Generate the previous month's branch report.
Schedule::command(
    'reports:monthly-branches --month=' .
    now('Asia/Damascus')->subMonth()->format('Y-m')
)
    ->monthlyOn(1, '00:10')
    ->timezone('Asia/Damascus')
    ->name('monthly-branch-report')
    ->withoutOverlapping()
    ->onOneServer()
    ->before(function () {
        Log::channel('schedule')->info('Scheduled task started.', [
            'task' => 'reports:monthly-branches',
            'started_at' => now()->toIso8601String(),
        ]);
    })
    ->onSuccess(function () {
        Log::channel('schedule')->info('Scheduled task completed.', [
            'task' => 'reports:monthly-branches',
            'completed_at' => now()->toIso8601String(),
            'status' => 'success',
        ]);
    })
    ->onFailure(function () {
        Log::channel('schedule')->error('Scheduled task failed.', [
            'task' => 'reports:monthly-branches',
            'failed_at' => now()->toIso8601String(),
            'status' => 'failed',
        ]);
    });


   