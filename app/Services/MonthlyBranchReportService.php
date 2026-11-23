<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class MonthlyBranchReportService
{
    public function generate(
        string $month,
        ?int $branchId = null,
        string $format = 'json',
        bool $dryRun = false
    ): array {
        $startDate = Carbon::createFromFormat('!Y-m', $month)
            ->startOfMonth()
            ->toDateString();

        $endDate = Carbon::createFromFormat('!Y-m', $month)
            ->endOfMonth()
            ->toDateString();

        $branches = Branch::query()
            ->when($branchId, fn ($query) => $query->whereKey($branchId))
            ->orderBy('id')
            ->get();

        $report = [];

        foreach ($branches as $branch) {
            $appointments = Appointment::withoutGlobalScopes()
                ->where('branch_id', $branch->id)
                ->whereBetween('appointment_date', [$startDate, $endDate]);

            $statusCounts = (clone $appointments)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $report[] = [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'branch_code' => $branch->code,
                'month' => $month,
                'total_appointments' => (clone $appointments)->count(),
                'booked' => (int) ($statusCounts[AppointmentStatus::BOOKED->value] ?? 0),
                'arrived' => (int) ($statusCounts[AppointmentStatus::ARRIVED->value] ?? 0),
                'completed' => (int) ($statusCounts[AppointmentStatus::COMPLETED->value] ?? 0),
                'cancelled' => (int) ($statusCounts[AppointmentStatus::CANCELLED->value] ?? 0),
                'no_show' => (int) ($statusCounts[AppointmentStatus::NO_SHOW->value] ?? 0),
                'doctors_count' => (clone $appointments)
                    ->distinct('doctor_id')
                    ->count('doctor_id'),
            ];
        }

        $filePath = null;

        if (!$dryRun) {
            $fileName = "branch-report-{$month}.{$format}";
            $filePath = "reports/{$fileName}";

            $content = $format === 'json'
                ? json_encode(
                    $report,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                )
                : $this->convertToCsv($report);

            Storage::disk('local')->put($filePath, $content);
        }

        return [
            'month' => $month,
            'branches_count' => count($report),
            'report' => $report,
            'file_path' => $filePath,
            'dry_run' => $dryRun,
        ];
    }

    private function convertToCsv(array $report): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new \RuntimeException('Unable to create CSV stream.');
        }

        if (!empty($report)) {
            fputcsv($stream, array_keys($report[0]));

            foreach ($report as $row) {
                fputcsv($stream, $row);
            }
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $content;
    }
}
