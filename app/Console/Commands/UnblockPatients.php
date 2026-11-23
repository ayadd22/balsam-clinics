<?php

namespace App\Console\Commands;

use App\Services\PatientUnblockService;
use Illuminate\Console\Command;
use Throwable;

class UnblockPatients extends Command
{
    protected $signature = 'patients:unblock-expired
        {--dry-run : Preview without updating patients}';

    protected $description = 'Unblock patients whose booking restriction has expired';

    public function handle(PatientUnblockService $service): int
    {
        try {
            $result = $service->unblockExpiredPatients(
                (bool) $this->option('dry-run')
            );

            $this->info("Expired blocked patients: {$result['total']}");

            if ($result['dry_run']) {
                $this->warn('Dry run: no patients were updated.');
            } else {
                $this->info("Patients unblocked: {$result['updated']}");
            }

            return self::SUCCESS;

        } catch (Throwable $exception) {
            report($exception);

            $this->error('Failed to unblock expired patients.');

            return self::FAILURE;
        }
    }
}
