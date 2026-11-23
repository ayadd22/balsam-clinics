<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Models\Doctor;
use App\Observers\AppointmentObserver;
use App\Observers\MedicalRecordObserver;
use App\Observers\DoctorObserver;
use App\Contracts\SmsServiceInterface;
use App\Services\LogSmsService;
use App\Models\DoctorSchedule;
use App\Observers\DoctorScheduleObserver;


class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            SmsServiceInterface::class,
            LogSmsService::class
        );
    }

    public function boot(): void
    {
        Appointment::observe(AppointmentObserver::class);
        MedicalRecord::observe(MedicalRecordObserver::class);
        Doctor::observe(DoctorObserver::class);
        DoctorSchedule::observe(DoctorScheduleObserver::class);
    }
}
