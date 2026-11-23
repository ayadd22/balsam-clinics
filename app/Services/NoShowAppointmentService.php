<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NoShowAppointmentService
{
    public function markNoShows(
        string $date,
        ?int $branchId = null,
        bool $dryRun = false
    ): array {
        $appointments = Appointment::withoutGlobalScopes()
            ->whereDate('appointment_date', $date)
            ->where('status', AppointmentStatus::BOOKED)
            ->when(
                $branchId,
                fn ($query) => $query->where('branch_id', $branchId)
            )
            ->get();

        $eligible = $appointments->filter(function ($appointment) {
            $endDateTime = Carbon::parse(
                $appointment->appointment_date->toDateString()
                . ' '
                . $appointment->end_time
            );

            return $endDateTime->lte(now());
        });

        $updated = 0;

        if (!$dryRun) {
            foreach ($eligible as $appointment) {
                $wasUpdated = DB::transaction(function () use ($appointment) {
                    $lockedAppointment = Appointment::withoutGlobalScopes()
                        ->lockForUpdate()
                        ->find($appointment->id);

                    if (
                        !$lockedAppointment ||
                        $lockedAppointment->status !== AppointmentStatus::BOOKED
                    ) {
                        return false;
                    }

                    $endDateTime = Carbon::parse(
                        $lockedAppointment->appointment_date->toDateString()
                        . ' '
                        . $lockedAppointment->end_time
                    );

                    if ($endDateTime->isFuture()) {
                        return false;
                    }

                    $lockedAppointment->update([
                        'status' => AppointmentStatus::NO_SHOW,
                    ]);

                    return true;
                });

                if ($wasUpdated) {
                    $updated++;
                }
            }
        }

        return [
            'total' => $appointments->count(),
            'eligible' => $eligible->count(),
            'updated' => $updated,
            'dry_run' => $dryRun,
        ];
    }
}
