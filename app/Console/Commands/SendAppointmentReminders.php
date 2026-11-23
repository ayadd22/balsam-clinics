<?php

namespace App\Console\Commands;

use App\Services\AppointmentReminderService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders
        {--date= : Appointment date (YYYY-MM-DD)}
        {--branch= : Branch ID}
        {--dry-run : Preview without creating reminders or sending SMS}';

    protected $description = 'Queue SMS reminders for booked appointments';

    public function handle(AppointmentReminderService $service): int
    {
        $date = $this->option('date') ?: now()->addDay()->toDateString();

        try {
            try {
              $parsedDate = Carbon::createFromFormat('!Y-m-d', $date);
              } catch (\Throwable) {
                $this->error('Invalid date. Use YYYY-MM-DD.');
             return self::INVALID;
            }

if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
    $this->error('Invalid date. Use YYYY-MM-DD.');
    return self::INVALID;
}

            $branchOption = $this->option('branch');

            if (
                $branchOption !== null &&
                (!ctype_digit((string) $branchOption) || (int) $branchOption < 1)
            ) {
                $this->error('Branch ID must be a positive integer.');
                return self::INVALID;
            }

            if ($branchOption !== null &&
                !\App\Models\Branch::whereKey((int) $branchOption)->exists()) {
                $this->error('The selected branch does not exist.');
                return self::INVALID;
            }

            $result = $service->sendReminders(
                $date,
                $branchOption !== null ? (int) $branchOption : null,
                (bool) $this->option('dry-run')
            );

            $this->info("Appointment date: {$date}");
            $this->info("Total eligible appointments: {$result['total']}");
            $this->info("Reminders queued or previewed: {$result['created']}");
            $this->info("Skipped (already reminded): {$result['skipped']}");

            if ($result['dry_run']) {
                $this->warn('Dry run: no reminders were created and no jobs were dispatched.');
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Failed to process appointment reminders.');
            return self::FAILURE;
        }
    }
}
