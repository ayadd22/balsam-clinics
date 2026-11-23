<?php

namespace App\Observers;

use App\Models\Doctor;
use App\Services\DoctorCacheService;
use App\Services\AppointmentAvailabilityService;
use Illuminate\Support\Facades\DB;

class DoctorObserver
{
    public function __construct(
        private DoctorCacheService $doctorCacheService,
        private AppointmentAvailabilityService $availabilityService
    ) {}

    public function created(Doctor $doctor): void
    {
        $this->clearDoctorCaches($doctor);
    }

    public function updated(Doctor $doctor): void
    {
        $this->clearDoctorCaches($doctor);
    }

    public function deleting(Doctor $doctor): void
    {
        $branchIds = $doctor->schedules()
            ->pluck('branch_id')
            ->unique()
            ->values()
            ->all();

        $doctorId = (int) $doctor->id;

        DB::afterCommit(function () use ($branchIds, $doctorId) {
            foreach ($branchIds as $branchId) {
                $this->doctorCacheService->clearBranch((int) $branchId);
            }

            $this->availabilityService->clearDoctor($doctorId);
        });
    }

    private function clearDoctorCaches(Doctor $doctor): void
    {
        $branchIds = $doctor->schedules()
            ->pluck('branch_id')
            ->unique()
            ->values()
            ->all();

        $doctorId = (int) $doctor->id;

        DB::afterCommit(function () use ($branchIds, $doctorId) {
            foreach ($branchIds as $branchId) {
                $this->doctorCacheService->clearBranch((int) $branchId);
            }

            $this->availabilityService->clearDoctor($doctorId);
        });
    }
}
