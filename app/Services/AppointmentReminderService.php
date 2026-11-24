<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Jobs\SendAppointmentReminderJob;

class AppointmentReminderService
{
    public function sendReminders(
        string $date,
        ?int $branchId = null,
        bool $dryRun = false
    ): array {
        $appointments = Appointment::withoutGlobalScopes()
            ->with(['patient'])
            ->whereDate('appointment_date', $date)
            ->where('status', AppointmentStatus::BOOKED)
            ->when(
                $branchId,
                fn ($query) => $query->where('branch_id', $branchId)
            )
            ->whereHas('patient', fn ($query) =>
                $query->whereNotNull('phone')
            )
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($appointments as $appointment) {
            $exists = $appointment->reminders()
                ->where('reminder_type', 'day_before')
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $created++;
                continue;
            }

            $reminder = DB::transaction(function () use ($appointment) {
                return AppointmentReminder::firstOrCreate(
                    [
                        'appointment_id' => $appointment->id,
                        'reminder_type' => 'day_before',
                    ],
                    [
                        'status' => 'pending',
                    ]
                );
            });

            if (!$reminder->wasRecentlyCreated) {
                $skipped++;
                continue;
            }

            SendAppointmentReminderJob::dispatch($reminder->id)
                ->onQueue('low')
                ->afterCommit();

            $created++;
        }

        return [
            'total' => $appointments->count(),
            'created' => $created,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
        ];
    }
}
