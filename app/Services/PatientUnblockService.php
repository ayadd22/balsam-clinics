<?php

namespace App\Services;

use App\Models\Patient;

class PatientUnblockService
{
    public function unblockExpiredPatients(bool $dryRun = false): array
    {
        $patients = Patient::query()
            ->where('is_booking_blocked', true)
            ->whereNotNull('blocked_until')
            ->where('blocked_until', '<=', now());

        $total = $patients->count();

        if ($dryRun) {
            return [
                'total' => $total,
                'updated' => 0,
                'dry_run' => true,
            ];
        }

        $updated = $patients->update([
            'is_booking_blocked' => false,
            'blocked_until' => null,
        ]);

        return [
            'total' => $total,
            'updated' => $updated,
            'dry_run' => false,
        ];
    }
}
