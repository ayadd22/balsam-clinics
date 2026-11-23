<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\User;
use App\Services\AppointmentAvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AppointmentAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createContext(): array
    {
        Cache::flush();

        $date = Carbon::now()->addWeeks(2)->next(Carbon::MONDAY);

        $branch = Branch::create([
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'city' => 'Damascus',
            'address' => 'Test Address',
            'is_active' => true,
        ]);

        $doctorUser = User::factory()->create();

        $doctor = Doctor::create([
            'user_id' => $doctorUser->id,
            'specialty' => 'general_medicine',
            'license_number' => 'DOC-' . uniqid(),
            'is_active' => true,
        ]);

        $patient = Patient::create([
            'full_name' => 'Test Patient',
            'phone' => '0999999999',
        ]);

        DoctorSchedule::create([
            'doctor_id' => $doctor->id,
            'branch_id' => $branch->id,
            'day_of_week' => 'monday',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_active' => true,
        ]);

        return compact('branch', 'doctor', 'patient', 'date');
    }

    private function createAppointment(
        array $context,
        string $status = 'booked',
        string $start = '10:00:00',
        string $end = '10:30:00',
        ?int $branchId = null
    ): Appointment {
        return Appointment::create([
            'patient_id' => $context['patient']->id,
            'doctor_id' => $context['doctor']->id,
            'branch_id' => $branchId ?? $context['branch']->id,
            'appointment_date' => $context['date']->toDateString(),
            'start_time' => $start,
            'end_time' => $end,
            'status' => $status,
            'reference_number' => 'APT-' . uniqid(),
        ]);
    }

    private function checkAvailability(
        array $context,
        string $start = '10:00:00',
        string $end = '10:30:00'
    ): bool {
        return app(AppointmentAvailabilityService::class)->isAvailable(
            $context['doctor']->id,
            $context['branch']->id,
            $context['date']->toDateString(),
            $start,
            $end
        );
    }

    public function test_time_is_available_when_doctor_has_schedule_and_no_conflict(): void
    {
        $context = $this->createContext();

        $this->assertTrue(
            $this->checkAvailability($context)
        );
    }

    public function test_time_is_unavailable_when_outside_doctor_schedule(): void
    {
        $context = $this->createContext();

        $this->assertFalse(
            $this->checkAvailability($context, '08:00:00', '08:30:00')
        );
    }

    public function test_time_is_unavailable_when_doctor_has_overlapping_appointment(): void
    {
        $context = $this->createContext();

        $this->createAppointment($context);

        $this->assertFalse(
            $this->checkAvailability($context, '10:15:00', '10:45:00')
        );
    }

    public function test_time_is_unavailable_when_doctor_has_appointment_at_another_branch(): void
    {
        $context = $this->createContext();

        $otherBranch = Branch::create([
            'name' => 'Other Branch',
            'code' => 'OTHER',
            'city' => 'Damascus',
            'address' => 'Other Address',
            'is_active' => true,
        ]);

        $this->createAppointment(
            $context,
            'booked',
            '10:00:00',
            '10:30:00',
            $otherBranch->id
        );

        $this->assertFalse(
            $this->checkAvailability($context, '10:15:00', '10:45:00')
        );
    }

    public function test_cancelled_appointment_does_not_block_availability(): void
    {
        $context = $this->createContext();

        $this->createAppointment(
            $context,
            AppointmentStatus::CANCELLED->value
        );

        $this->assertTrue(
            $this->checkAvailability($context)
        );
    }

    public function test_inactive_doctor_is_not_available(): void
    {
        $context = $this->createContext();

        $context['doctor']->update([
            'is_active' => false,
        ]);

        $this->assertFalse(
            $this->checkAvailability($context)
        );
    }
}
