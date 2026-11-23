<?php

namespace App\Observers;

use App\Models\DoctorSchedule;
use App\Services\DoctorCacheService;
use App\Services\AppointmentAvailabilityService;
use Illuminate\Support\Facades\DB;

class DoctorScheduleObserver
{
    public function __construct(
        private DoctorCacheService $doctorCacheService,
        private AppointmentAvailabilityService $availabilityService
    ) {}

    public function created(DoctorSchedule $schedule): void
    {
        $this->clearCache($schedule);
    }

    public function updated(DoctorSchedule $schedule): void
    {
        $this->clearCache($schedule);
    }

    public function deleted(DoctorSchedule $schedule): void
    {
        $this->clearCache($schedule);
    }

    private function clearCache(DoctorSchedule $schedule): void
    {
        $doctorIds = array_unique([
            (int) $schedule->getOriginal('doctor_id'),
            (int) $schedule->doctor_id,
        ]);

        $branchIds = array_unique([
            (int) $schedule->getOriginal('branch_id'),
            (int) $schedule->branch_id,
        ]);

        DB::afterCommit(function () use ($doctorIds, $branchIds) {
            foreach ($branchIds as $branchId) {
                if ($branchId > 0) {
                    $this->doctorCacheService->clearBranch($branchId);
                }
            }

            foreach ($doctorIds as $doctorId) {
                if ($doctorId > 0) {
                    $this->availabilityService->clearDoctor($doctorId);
                }
            }
        });
    }
}
