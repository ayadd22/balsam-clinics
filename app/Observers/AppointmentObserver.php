<?php

namespace App\Observers;

use App\Models\Appointment;
use App\Services\AuditLogService;

class AppointmentObserver
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    public function created(Appointment $appointment): void
    {
        $this->auditLogService->created($appointment);
    }

    public function updated(Appointment $appointment): void
    {
        $this->auditLogService->updated($appointment);
    }

    public function deleted(Appointment $appointment): void
    {
        $this->auditLogService->deleted($appointment);
    }
}
