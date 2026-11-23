<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\Services\NoShowAppointmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class NoShowAppointmentServiceTest extends TestCase
{
    use DatabaseMigrations;

    private function createAppointment(
        string $endTime,
        AppointmentStatus $status = AppointmentStatus::BOOKED
    ): Appointment {
        $branch = Branch::create([
            'name' => 'Test Branch',
            'code' => 'BR-' . strtoupper(uniqid()),
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

        return Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'branch_id' => $branch->id,
            'appointment_date' => now()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => $endTime,
            'status' => $status,
            'reference_number' => 'APT-' . uniqid(),
        ]);
    }

    public function test_past_booked_appointment_is_marked_as_no_show(): void
    {
        Carbon::setTestNow('2026-11-23 16:00:00');

        $appointment = $this->createAppointment('15:00:00');

        $result = app(NoShowAppointmentService::class)->markNoShows(
            '2026-11-23'
        );

        $this->assertSame(1, $result['eligible']);
        $this->assertSame(1, $result['updated']);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => AppointmentStatus::NO_SHOW->value,
        ]);

        Carbon::setTestNow();
    }

    public function test_future_appointment_remains_booked(): void
    {
        Carbon::setTestNow('2026-11-23 10:00:00');

        $appointment = $this->createAppointment('11:00:00');

        $result = app(NoShowAppointmentService::class)->markNoShows(
            '2026-11-23'
        );

        $this->assertSame(0, $result['eligible']);
        $this->assertSame(0, $result['updated']);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => AppointmentStatus::BOOKED->value,
        ]);

        Carbon::setTestNow();
    }

    public function test_cancelled_appointment_is_not_changed(): void
    {
        Carbon::setTestNow('2026-11-23 16:00:00');

        $appointment = $this->createAppointment(
            '15:00:00',
            AppointmentStatus::CANCELLED
        );

        $result = app(NoShowAppointmentService::class)->markNoShows(
            '2026-11-23'
        );

        $this->assertSame(0, $result['eligible']);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => AppointmentStatus::CANCELLED->value,
        ]);

        Carbon::setTestNow();
    }

    public function test_dry_run_does_not_update_appointments(): void
    {
        Carbon::setTestNow('2026-11-23 16:00:00');

        $appointment = $this->createAppointment('15:00:00');

        $result = app(NoShowAppointmentService::class)->markNoShows(
            '2026-11-23',
            null,
            true
        );

        $this->assertSame(1, $result['eligible']);
        $this->assertSame(0, $result['updated']);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => AppointmentStatus::BOOKED->value,
        ]);

        Carbon::setTestNow();
    }
}
