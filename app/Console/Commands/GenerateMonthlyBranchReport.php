<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Services\MonthlyBranchReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class GenerateMonthlyBranchReport extends Command
{
    protected $signature = 'reports:monthly-branches
        {--month= : Report month (YYYY-MM)}
        {--branch= : Optional branch ID}
        {--format=json : Report format (json or csv)}
        {--dry-run : Preview without creating a file}';

    protected $description = 'Generate monthly appointment reports for branches';

    public function handle(MonthlyBranchReportService $service): int
    {
        $month = $this->option('month') ?: now()->format('Y-m');
        $format = strtolower($this->option('format'));

        try {
            try {
                $parsedMonth = Carbon::createFromFormat('!Y-m', $month);
            } catch (Throwable) {
                $this->error('Invalid month. Use YYYY-MM.');

                return self::INVALID;
            }

            if (
                !$parsedMonth ||
                $parsedMonth->format('Y-m') !== $month
            ) {
                $this->error('Invalid month. Use YYYY-MM.');

                return self::INVALID;
            }

            if (!in_array($format, ['json', 'csv'], true)) {
                $this->error('Invalid format. Use json or csv.');

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

            $result = $service->generate(
                $month,
                $branchOption !== null ? (int) $branchOption : null,
                $format,
                (bool) $this->option('dry-run')
            );

            $this->info("Report month: {$result['month']}");
            $this->info("Branches included: {$result['branches_count']}");

            foreach ($result['report'] as $row) {
                $this->line(
                    "{$row['branch_name']}: "
                    . "{$row['total_appointments']} appointments, "
                    . "{$row['completed']} completed, "
                    . "{$row['cancelled']} cancelled, "
                    . "{$row['no_show']} no-show"
                );
            }

            if ($result['dry_run']) {
                $this->warn('Dry run: no report file was created.');
            } else {
                $this->info("Report saved to: storage/app/{$result['file_path']}");
            }

            return self::SUCCESS;

        } catch (Throwable $exception) {
            report($exception);

            $this->error('Failed to generate monthly branch report.');

            return self::FAILURE;
        }
    }
}