<?php

namespace App\Observers;

use App\Models\Appointment;
use App\Models\AppointmentSequence;
use App\Models\Branch;
use App\Services\AuditLogService;
use App\Services\AppointmentAvailabilityService;
use Illuminate\Support\Facades\DB;

class AppointmentObserver
{
    public function __construct(
        private AuditLogService $auditLogService,
        private AppointmentAvailabilityService $availabilityService
    ) {}

    public function creating(Appointment $appointment): void
    {
        $branch = Branch::query()->findOrFail($appointment->branch_id);

        $year = (int) date(
            'Y',
            strtotime($appointment->appointment_date)
        );

        $referenceNumber = DB::transaction(function () use ($branch, $year) {
            $now = now();

            DB::table('appointment_sequences')->insertOrIgnore([
                'branch_id' => $branch->id,
                'year' => $year,
                'last_number' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $sequence = AppointmentSequence::query()
                ->where('branch_id', $branch->id)
                ->where('year', $year)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->last_number++;
            $sequence->save();

            return sprintf(
                'APT-%s-%d-%06d',
                strtoupper($branch->code),
                $year,
                $sequence->last_number
            );
        });

        // The observer owns reference generation.
        $appointment->reference_number = $referenceNumber;
    }

    public function created(Appointment $appointment): void
    {
        $this->auditLogService->created($appointment);

        $this->clearAvailabilityCache([$appointment->doctor_id]);
    }

    public function updated(Appointment $appointment): void
    {
        $this->auditLogService->updated($appointment);

        $availabilityFields = [
            'doctor_id',
            'branch_id',
            'appointment_date',
            'start_time',
            'end_time',
            'status',
        ];

        if (!$appointment->wasChanged($availabilityFields)) {
            return;
        }

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
