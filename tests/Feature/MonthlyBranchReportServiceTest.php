<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\Services\MonthlyBranchReportService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MonthlyBranchReportServiceTest extends TestCase
{
    use DatabaseMigrations;

    private function createBranch(string $code): Branch
    {
        return Branch::create([
            'name' => 'Branch ' . $code,
            'code' => $code,
            'city' => 'Damascus',
            'address' => 'Test Address',
            'is_active' => true,
        ]);
    }

    private function createAppointment(
        Branch $branch,
        Doctor $doctor,
        AppointmentStatus $status
    ): Appointment {
        $patient = Patient::create([
            'full_name' => 'Test Patient',
            'phone' => '0999999999',
        ]);

        return Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'branch_id' => $branch->id,
            'appointment_date' => '2026-11-15',
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'status' => $status,
            'reference_number' => 'APT-' . uniqid(),
        ]);
    }

    private function createDoctor(): Doctor
    {
        $user = User::factory()->create();

        return Doctor::create([
            'user_id' => $user->id,
            'specialty' => 'general_medicine',
            'license_number' => 'DOC-' . uniqid(),
            'is_active' => true,
        ]);
    }

    public function test_monthly_report_calculates_branch_statistics(): void
    {
        $branch = $this->createBranch('BR-' . uniqid());
        $doctor = $this->createDoctor();

        $this->createAppointment(
            $branch,
            $doctor,
            AppointmentStatus::COMPLETED
        );

        $this->createAppointment(
            $branch,
            $doctor,
            AppointmentStatus::NO_SHOW
        );

        $result = app(MonthlyBranchReportService::class)->generate(
            '2026-11',
            $branch->id,
            'json',
            true
        );

        $this->assertSame(1, $result['branches_count']);

        $report = $result['report'][0];

        $this->assertSame(2, $report['total_appointments']);
        $this->assertSame(1, $report['completed']);
        $this->assertSame(1, $report['no_show']);
        $this->assertSame(1, $report['doctors_count']);
    }

    public function test_report_can_be_filtered_by_branch(): void
    {
        $firstBranch = $this->createBranch('BR-' . uniqid());
        $secondBranch = $this->createBranch('BR-' . uniqid());

        $doctor = $this->createDoctor();

        $this->createAppointment(
            $firstBranch,
            $doctor,
            AppointmentStatus::COMPLETED
        );

        $this->createAppointment(
            $secondBranch,
            $doctor,
            AppointmentStatus::BOOKED
        );

        $result = app(MonthlyBranchReportService::class)->generate(
            '2026-11',
            $firstBranch->id,
            'json',
            true
        );

        $this->assertSame(1, $result['branches_count']);
        $this->assertSame(
            $firstBranch->id,
            $result['report'][0]['branch_id']
        );
    }

    public function test_json_report_file_is_created(): void
    {
        Storage::fake('local');

        $branch = $this->createBranch('BR-' . uniqid());

        $result = app(MonthlyBranchReportService::class)->generate(
            '2026-11',
            $branch->id,
            'json'
        );

        $this->assertSame(
            'reports/branch-report-2026-11.json',
            $result['file_path']
        );

        Storage::disk('local')->assertExists($result['file_path']);

        $content = json_decode(
            Storage::disk('local')->get($result['file_path']),
            true
        );

        $this->assertSame($branch->id, $content[0]['branch_id']);
    }

    public function test_csv_report_file_is_created(): void
    {
        Storage::fake('local');

        $branch = $this->createBranch('BR-' . uniqid());

        $result = app(MonthlyBranchReportService::class)->generate(
            '2026-11',
            $branch->id,
            'csv'
        );

        Storage::disk('local')->assertExists($result['file_path']);

        $content = Storage::disk('local')->get($result['file_path']);

        $this->assertStringContainsString(
            'branch_id,branch_name',
            $content
        );
    }

    public function test_dry_run_does_not_create_report_file(): void
    {
        Storage::fake('local');

        $branch = $this->createBranch('BR-' . uniqid());

        $result = app(MonthlyBranchReportService::class)->generate(
            '2026-11',
            $branch->id,
            'json',
            true
        );

        $this->assertTrue($result['dry_run']);
        $this->assertNull($result['file_path']);

        Storage::disk('local')->assertMissing(
            'reports/branch-report-2026-11.json'
        );
    }
}
