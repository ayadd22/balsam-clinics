<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class AppointmentAvailabilityService
{
    private int $ttl = 300;

    public function isAvailable(
        int $doctorId,
        int $branchId,
        string $date,
        string $startTime,
        string $endTime
    ): bool {
        $versionKey = "appointment-availability:doctor:{$doctorId}:version";
        $version = Cache::get($versionKey, 1);

        $cacheKey = "appointment-availability:doctor:{$doctorId}"
            . ":branch:{$branchId}"
            . ":date:{$date}"
            . ":start:{$startTime}"
            . ":end:{$endTime}"
            . ":version:{$version}";

        return Cache::remember($cacheKey, $this->ttl, function () use (
            $doctorId,
            $branchId,
            $date,
            $startTime,
            $endTime
        ) {
            $doctorIsActive = Doctor::query()
                ->whereKey($doctorId)
                ->where('is_active', true)
                ->exists();

            if (!$doctorIsActive) {
                return false;
            }

            $dayOfWeek = strtolower(Carbon::parse($date)->format('l'));

            $hasSchedule = DoctorSchedule::query()
                ->where('doctor_id', $doctorId)
                ->where('branch_id', $branchId)
                ->where('day_of_week', $dayOfWeek)
                ->where('is_active', true)
                ->where('start_time', '<=', $startTime)
                ->where('end_time', '>=', $endTime)
                ->exists();

            if (!$hasSchedule) {
                return false;
            }

            $hasConflict = Appointment::withoutGlobalScopes()
                ->where('doctor_id', $doctorId)
                ->whereDate('appointment_date', $date)
                ->where('status', '!=', AppointmentStatus::CANCELLED->value)
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime)
                ->exists();

            return !$hasConflict;
        });
    }

    public function clearDoctor(int $doctorId): void
    {
        $versionKey = "appointment-availability:doctor:{$doctorId}:version";

        Cache::add($versionKey, 1);
        Cache::increment($versionKey);
    }
}
