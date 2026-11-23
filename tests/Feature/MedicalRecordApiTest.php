<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MedicalRecordApiTest extends TestCase
{
    use RefreshDatabase;

    private function createContext(): array
    {
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
            'license_number' => 'DOC-' . uniqid(),
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

        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'branch_id' => $branch->id,
            'appointment_date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'status' => AppointmentStatus::COMPLETED,
            'reference_number' => 'APT-' . uniqid(),
        ]);

        return compact(
            'branch',
            'doctor',
            'doctorUser',
            'patient',
            'patientUser',
            'appointment'
        );
    }

    private function createMedicalRecord(array $context): MedicalRecord
    {
        return MedicalRecord::create([
            'appointment_id' => $context['appointment']->id,
            'doctor_id' => $context['doctor']->id,
            'diagnosis' => 'Test diagnosis',
            'prescription' => 'Test prescription',
            'notes' => 'Test notes',
        ]);
    }

    public function test_doctor_can_create_medical_record_for_own_completed_appointment(): void
    {
        $context = $this->createContext();

        Sanctum::actingAs($context['doctorUser']);

        $response = $this->postJson('/api/medical-records', [
            'appointment_id' => $context['appointment']->id,
            'diagnosis' => 'Flu',
            'prescription' => 'Medicine',
            'notes' => 'Rest for one week',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('medical_records', [
            'appointment_id' => $context['appointment']->id,
            'doctor_id' => $context['doctor']->id,
            'diagnosis' => 'Flu',
        ]);
    }

    public function test_doctor_cannot_create_record_for_another_doctors_appointment(): void
    {
        $context = $this->createContext();

        $anotherDoctorUser = User::factory()->create([
            'role' => UserRole::DOCTOR,
        ]);

        Doctor::create([
            'user_id' => $anotherDoctorUser->id,
            'specialty' => 'cardiology',
            'license_number' => 'DOC-' . uniqid(),
            'is_active' => true,
        ]);

        Sanctum::actingAs($anotherDoctorUser);

        $response = $this->postJson('/api/medical-records', [
            'appointment_id' => $context['appointment']->id,
            'diagnosis' => 'Flu',
        ]);

        $response->assertNotFound();

        $this->assertDatabaseCount('medical_records', 0);
    }

    public function test_medical_record_cannot_be_created_for_uncompleted_appointment(): void
    {
        $context = $this->createContext();

        $context['appointment']->update([
            'status' => AppointmentStatus::BOOKED,
        ]);

        Sanctum::actingAs($context['doctorUser']);

        $response = $this->postJson('/api/medical-records', [
            'appointment_id' => $context['appointment']->id,
            'diagnosis' => 'Flu',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('appointment_id');

        $this->assertDatabaseCount('medical_records', 0);
    }

    public function test_duplicate_medical_record_cannot_be_created_for_same_appointment(): void
    {
        $context = $this->createContext();

        $this->createMedicalRecord($context);

        Sanctum::actingAs($context['doctorUser']);

        $response = $this->postJson('/api/medical-records', [
            'appointment_id' => $context['appointment']->id,
            'diagnosis' => 'Another diagnosis',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('appointment_id');

        $this->assertDatabaseCount('medical_records', 1);
    }

    public function test_patient_can_view_own_medical_record(): void
    {
        $context = $this->createContext();

        $record = $this->createMedicalRecord($context);

        Sanctum::actingAs($context['patientUser']);

        $this->getJson("/api/medical-records/{$record->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $record->id);
    }

    public function test_patient_cannot_view_another_patients_medical_record(): void
    {
        $context = $this->createContext();

        $record = $this->createMedicalRecord($context);

        $anotherPatient = User::factory()->create([
            'role' => UserRole::PATIENT,
        ]);

        $anotherPatient->patient()->create([
            'full_name' => 'Another Patient',
            'phone' => '0988888888',
        ]);

        Sanctum::actingAs($anotherPatient);

        $this->getJson("/api/medical-records/{$record->id}")
            ->assertForbidden();
    }

    public function test_patient_cannot_create_medical_record(): void
    {
        $context = $this->createContext();

        Sanctum::actingAs($context['patientUser']);

        $this->postJson('/api/medical-records', [
            'appointment_id' => $context['appointment']->id,
            'diagnosis' => 'Flu',
        ])->assertForbidden();

        $this->assertDatabaseCount('medical_records', 0);
    }

    public function test_patient_cannot_update_medical_record(): void
    {
        $context = $this->createContext();

        $record = $this->createMedicalRecord($context);

        Sanctum::actingAs($context['patientUser']);

        $this->putJson("/api/medical-records/{$record->id}", [
            'diagnosis' => 'Changed diagnosis',
        ])->assertForbidden();

        $this->assertDatabaseHas('medical_records', [
            'id' => $record->id,
            'diagnosis' => 'Test diagnosis',
        ]);
    }

    public function test_doctor_can_update_own_medical_record(): void
    {
        $context = $this->createContext();

        $record = $this->createMedicalRecord($context);

        Sanctum::actingAs($context['doctorUser']);

        $this->putJson("/api/medical-records/{$record->id}", [
            'diagnosis' => 'Updated diagnosis',
        ])->assertOk();

        $this->assertDatabaseHas('medical_records', [
            'id' => $record->id,
            'diagnosis' => 'Updated diagnosis',
        ]);
    }

    public function test_doctor_cannot_update_another_doctors_record(): void
    {
        $context = $this->createContext();

        $record = $this->createMedicalRecord($context);

        $anotherDoctorUser = User::factory()->create([
            'role' => UserRole::DOCTOR,
        ]);

        Doctor::create([
            'user_id' => $anotherDoctorUser->id,
            'specialty' => 'cardiology',
            'license_number' => 'DOC-' . uniqid(),
            'is_active' => true,
        ]);

        Sanctum::actingAs($anotherDoctorUser);

        $this->putJson("/api/medical-records/{$record->id}", [
            'diagnosis' => 'Changed diagnosis',
        ])->assertForbidden();

        $this->assertDatabaseHas('medical_records', [
            'id' => $record->id,
            'diagnosis' => 'Test diagnosis',
        ]);
    }

    public function test_admin_can_view_and_update_medical_record(): void
    {
        $context = $this->createContext();

        $record = $this->createMedicalRecord($context);

        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson("/api/medical-records/{$record->id}")
            ->assertOk();

        $this->putJson("/api/medical-records/{$record->id}", [
            'diagnosis' => 'Admin updated diagnosis',
        ])->assertOk();

        $this->assertDatabaseHas('medical_records', [
            'id' => $record->id,
            'diagnosis' => 'Admin updated diagnosis',
        ]);
    }

    public function test_receptionist_cannot_access_medical_records(): void
    {
        $context = $this->createContext();

        $receptionist = User::factory()->create([
            'role' => UserRole::RECEPTIONIST,
        ]);

        Sanctum::actingAs($receptionist);

        $this->getJson('/api/medical-records')
            ->assertForbidden();
    }
}
