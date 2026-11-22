<?php

namespace App\Observers;

use App\Models\MedicalRecord;
use App\Services\AuditLogService;

class MedicalRecordObserver
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    public function created(MedicalRecord $medicalRecord): void
    {
        $this->auditLogService->created($medicalRecord);
    }

    public function updated(MedicalRecord $medicalRecord): void
    {
        $this->auditLogService->updated($medicalRecord);
    }

    public function deleted(MedicalRecord $medicalRecord): void
    {
        $this->auditLogService->deleted($medicalRecord);
    }
}
