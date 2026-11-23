<?php

namespace App\Observers;

use App\Models\Appointment;
use App\Services\AuditLogService;
use App\Services\AppointmentAvailabilityService;
use Illuminate\Support\Facades\DB;

class AppointmentObserver
{
    public function __construct(
        private AuditLogService $auditLogService,
        private AppointmentAvailabilityService $availabilityService
    ) {}

    public function created(Appointment $appointment): void
    {
        $this->auditLogService->created($appointment);

        $this->clearAvailabilityCache([$appointment->doctor_id]);
    }

    public function updated(Appointment $appointment): void
    {
        $this->auditLogService->updated($appointment);

        $this->clearAvailabilityCache([
            $appointment->getOriginal('doctor_id'),
            $appointment->doctor_id,
        ]);
    }

    public function deleted(Appointment $appointment): void
    {
        $this->auditLogService->deleted($appointment);

        $this->clearAvailabilityCache([$appointment->doctor_id]);
    }

    private function clearAvailabilityCache(array $doctorIds): void
    {
        $doctorIds = array_unique(array_filter($doctorIds));

        DB::afterCommit(function () use ($doctorIds) {
            foreach ($doctorIds as $doctorId) {
                $this->availabilityService->clearDoctor((int) $doctorId);
            }
        });
    }
}
