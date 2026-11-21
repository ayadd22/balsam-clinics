<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Appointment;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AppointmentPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_any_appointment(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
        ]);

        $this->assertTrue($admin->can('viewAny', Appointment::class));
    }

    public function test_patient_can_view_only_own_appointment(): void
    {
        $patientUser = User::factory()->create([
            'role' => UserRole::PATIENT,
        ]);

        $otherPatientUser = User::factory()->create([
            'role' => UserRole::PATIENT,
        ]);

        $patient = Patient::create([
            'user_id' => $patientUser->id,
            'full_name' => 'Aya',
            'phone' => '0991111111',
        ]);

        $otherPatient = Patient::create([
            'user_id' => $otherPatientUser->id,
            'full_name' => 'Other',
            'phone' => '0992222222',
        ]);

        $branch = Branch::create([
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'city' => 'Damascus',
            'address' => 'Street 1',
            'is_active' => true,
        ]);

        $doctorUser = User::factory()->create([
            'role' => UserRole::DOCTOR,
        ]);

        $doctor = Doctor::create([
            'user_id' => $doctorUser->id,
            'specialty' => 'cardiology',
            'license_number' => 'DOC-001',
            'is_active' => true,
        ]);

        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'branch_id' => $branch->id,
            'appointment_date' => '2026-12-01',
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'status' => 'booked',
            'reference_number' => 'APT-001',
        ]);

        $this->assertTrue($patientUser->can('view', $appointment));

        $this->assertFalse($otherPatientUser->can('view', $appointment));
    }

    public function test_doctor_can_view_only_own_appointment(): void
    {
        $doctorUser = User::factory()->create([
            'role' => UserRole::DOCTOR,
        ]);

        $otherDoctorUser = User::factory()->create([
            'role' => UserRole::DOCTOR,
        ]);

        $doctor = Doctor::create([
            'user_id' => $doctorUser->id,
            'specialty' => 'cardiology',
            'license_number' => 'DOC-002',
            'is_active' => true,
        ]);

        $otherDoctor = Doctor::create([
            'user_id' => $otherDoctorUser->id,
            'specialty' => 'dermatology',
            'license_number' => 'DOC-003',
            'is_active' => true,
        ]);

        $patientUser = User::factory()->create();

        $patient = Patient::create([
            'user_id' => $patientUser->id,
            'full_name' => 'Patient',
            'phone' => '0993333333',
        ]);

        $branch = Branch::create([
            'name' => 'Branch',
            'code' => 'BR01',
            'city' => 'Damascus',
            'address' => 'Street 2',
            'is_active' => true,
        ]);

        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'branch_id' => $branch->id,
            'appointment_date' => '2026-12-01',
            'start_time' => '11:00:00',
            'end_time' => '11:30:00',
            'status' => 'booked',
            'reference_number' => 'APT-002',
        ]);

        $this->assertTrue($doctorUser->can('view', $appointment));

        $this->assertFalse($otherDoctorUser->can('view', $appointment));
    }

    public function test_receptionist_can_view_appointments_only_in_assigned_branches(): void
    {
        $receptionist = User::factory()->create([
            'role' => UserRole::RECEPTIONIST,
        ]);

        $assignedBranch = Branch::create([
            'name' => 'Assigned Branch',
            'code' => 'BR02',
            'city' => 'Damascus',
            'address' => 'Street 3',
            'is_active' => true,
        ]);

        $otherBranch = Branch::create([
            'name' => 'Other Branch',
            'code' => 'BR03',
            'city' => 'Damascus',
            'address' => 'Street 4',
            'is_active' => true,
        ]);

        $receptionist->branches()->attach($assignedBranch->id);

        $patientUser = User::factory()->create();

        $patient = Patient::create([
            'user_id' => $patientUser->id,
            'full_name' => 'Patient',
            'phone' => '0994444444',
        ]);

        $doctorUser = User::factory()->create([
            'role' => UserRole::DOCTOR,
        ]);

        $doctor = Doctor::create([
            'user_id' => $doctorUser->id,
            'specialty' => 'cardiology',
            'license_number' => 'DOC-004',
            'is_active' => true,
        ]);

        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'branch_id' => $assignedBranch->id,
            'appointment_date' => '2026-12-02',
            'start_time' => '12:00:00',
            'end_time' => '12:30:00',
            'status' => 'booked',
            'reference_number' => 'APT-003',
        ]);

        $otherAppointment = Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'branch_id' => $otherBranch->id,
            'appointment_date' => '2026-12-02',
            'start_time' => '13:00:00',
            'end_time' => '13:30:00',
            'status' => 'booked',
            'reference_number' => 'APT-004',
        ]);

        $this->assertTrue($receptionist->can('view', $appointment));

        $this->assertFalse($receptionist->can('view', $otherAppointment));
    }
}
