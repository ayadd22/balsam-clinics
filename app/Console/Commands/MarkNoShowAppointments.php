<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Services\NoShowAppointmentService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class MarkNoShowAppointments extends Command
{
    protected $signature = 'appointments:mark-no-shows
        {--date= : Appointment date (YYYY-MM-DD)}
        {--branch= : Branch ID}
        {--dry-run : Preview without updating appointments}';

    protected $description = 'Mark past booked appointments as no-show';

    public function handle(NoShowAppointmentService $service): int
    {
        $date = $this->option('date') ?: now()->toDateString();

        try {
            try {
                $parsedDate = Carbon::createFromFormat('!Y-m-d', $date);
            } catch (Throwable) {
                $this->error('Invalid date. Use YYYY-MM-DD.');

                return self::INVALID;
            }

            if (
                !$parsedDate ||
                $parsedDate->format('Y-m-d') !== $date
            ) {
                $this->error('Invalid date. Use YYYY-MM-DD.');

                return self::INVALID;
            }

            $branchOption = $this->option('branch');

            if (
                $branchOption !== null &&
                (!ctype_digit((string) $branchOption) ||
                    (int) $branchOption < 1)
            ) {
                $this->error('Branch ID must be a positive integer.');

                return self::INVALID;
            }

            if (
                $branchOption !== null &&
                !Branch::whereKey((int) $branchOption)->exists()
            ) {
                $this->error('The selected branch does not exist.');

                return self::INVALID;
            }

            $result = $service->markNoShows(
                $date,
                $branchOption !== null ? (int) $branchOption : null,
                (bool) $this->option('dry-run')
            );

            $this->info("Appointment date: {$date}");
            $this->info("Total booked appointments: {$result['total']}");
            $this->info("Eligible for no-show: {$result['eligible']}");

            if ($result['dry_run']) {
                $this->warn(
                    'Dry run: no appointments were updated.'
                );
            } else {
                $this->info("Appointments marked as no-show: {$result['updated']}");
            }

            return self::SUCCESS;

        } catch (Throwable $exception) {
            report($exception);

            $this->error('Failed to process no-show appointments.');

            return self::FAILURE;
        }
    }
}
