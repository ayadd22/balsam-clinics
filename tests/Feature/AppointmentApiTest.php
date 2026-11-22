<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentApiTest extends TestCase
{
    use RefreshDatabase;

    private function createBookingContext(): array
    {
        $date = Carbon::now()->addWeeks(2)->next(Carbon::MONDAY);

        $branch = Branch::create([
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'city' => 'Damascus',
            'address' => 'Test Address',
            'is_active' => true,
        ]);

        $doctorUser = User::factory()->create([
            'role' => UserRole::DOCTOR,
        ]);

        $doctor = Doctor::create([
            'user_id' => $doctorUser->id,
            'specialty' => 'general_medicine',
            'license_number' => 'DOC-001',
            'is_active' => true,
        ]);

        DoctorSchedule::create([
            'doctor_id' => $doctor->id,
            'branch_id' => $branch->id,
            'day_of_week' => 'monday',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_active' => true,
        ]);

        $patientUser = User::factory()->create([
            'role' => UserRole::PATIENT,
        ]);

        $patient = Patient::create([
            'user_id' => $patientUser->id,
            'full_name' => $patientUser->name,
            'phone' => '0999999999',
        ]);

        return compact(
            'date',
            'branch',
            'doctor',
            'patient',
            'patientUser'
        );
    }

    private function bookingData(array $context): array
    {
        return [
            'doctor_id' => $context['doctor']->id,
            'branch_id' => $context['branch']->id,
            'appointment_date' => $context['date']->toDateString(),
            'start_time' => '10:00',
            'end_time' => '10:30',
        ];
    }

    public function test_patient_can_book_appointment_for_themselves(): void
    {
        $context = $this->createBookingContext();

        Sanctum::actingAs($context['patientUser']);

        $response = $this->postJson(
            '/api/appointments',
            $this->bookingData($context)
        );

        $response->assertCreated();

        $this->assertDatabaseHas('appointments', [
            'patient_id' => $context['patient']->id,
            'doctor_id' => $context['doctor']->id,
            'branch_id' => $context['branch']->id,
            'status' => 'booked',
        ]);
    }

    public function test_patient_cannot_choose_another_patient_id(): void
    {
        $context = $this->createBookingContext();

        $anotherPatient = Patient::create([
            'full_name' => 'Another Patient',
            'phone' => '0988888888',
        ]);

        Sanctum::actingAs($context['patientUser']);

        $data = $this->bookingData($context);
        $data['patient_id'] = $anotherPatient->id;

        $response = $this->postJson('/api/appointments', $data);

        $response->assertUnprocessable();

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_admin_can_book_appointment_for_a_patient(): void
    {
        $context = $this->createBookingContext();

        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
        ]);

        Sanctum::actingAs($admin);

        $data = $this->bookingData($context);
        $data['patient_id'] = $context['patient']->id;

        $response = $this->postJson('/api/appointments', $data);

        $response->assertCreated();

        $this->assertDatabaseHas('appointments', [
            'patient_id' => $context['patient']->id,
            'doctor_id' => $context['doctor']->id,
        ]);
    }

    public function test_receptionist_can_book_appointment_for_a_patient(): void
    {
        $context = $this->createBookingContext();

        $receptionist = User::factory()->create([
            'role' => UserRole::RECEPTIONIST,
        ]);

        Sanctum::actingAs($receptionist);

        $data = $this->bookingData($context);
        $data['patient_id'] = $context['patient']->id;

        $response = $this->postJson('/api/appointments', $data);

        $response->assertCreated();

        $this->assertDatabaseHas('appointments', [
            'patient_id' => $context['patient']->id,
            'doctor_id' => $context['doctor']->id,
        ]);
    }

    public function test_guest_cannot_create_appointment(): void
    {
        $context = $this->createBookingContext();

        $response = $this->postJson(
            '/api/appointments',
            $this->bookingData($context)
        );

        $response->assertUnauthorized();

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_doctor_cannot_create_appointment(): void
    {
        $context = $this->createBookingContext();

        $doctorUser = $context['doctor']->user;

        Sanctum::actingAs($doctorUser);

        $response = $this->postJson(
            '/api/appointments',
            $this->bookingData($context)
        );

        $response->assertForbidden();

        $this->assertDatabaseCount('appointments', 0);
    }
}

