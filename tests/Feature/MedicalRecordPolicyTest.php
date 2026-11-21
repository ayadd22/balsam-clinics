<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Branch;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Enums\UserRole;
use App\Enums\Specialty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class MedicalRecordPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithRole(UserRole $role): User
    {
        $user = User::factory()->create();

        $user->forceFill([
            'role' => $role,
        ])->save();

        return $user;
    }

    private function createDoctor(User $user): Doctor
    {
        return Doctor::create([
            'user_id' => $user->id,
            'specialty' => Specialty::GENERAL_MEDICINE,
            'license_number' => Str::random(15),
            'is_active' => true,
        ]);
    }

    private function createPatient(User $user): Patient
    {
        return Patient::create([
            'user_id' => $user->id,
            'full_name' => fake()->name(),
            'phone' => fake()->unique()->phoneNumber(),
        ]);
    }

    private function createBranch(): Branch
    {
        return Branch::create([
            'name' => 'Test Branch',
            'code' => Str::upper(Str::random(8)),
            'city' => 'Damascus',
            'address' => 'Test Address',
            'is_active' => true,
        ]);
    }

    private function createMedicalRecord(
        Doctor $doctor,
        Patient $patient
    ): MedicalRecord {
        $branch = $this->createBranch();

        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'branch_id' => $branch->id,
            'appointment_date' => now()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'reference_number' => Str::uuid()->toString(),
        ]);

        return MedicalRecord::create([
            'appointment_id' => $appointment->id,
            'doctor_id' => $doctor->id,
            'diagnosis' => 'Test Diagnosis',
        ]);
    }

    public function test_admin_can_view_any_medical_record(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);

        $this->assertTrue(
            $admin->can('viewAny', MedicalRecord::class)
        );
    }

    public function test_doctor_can_view_any_medical_record(): void
    {
        $doctor = $this->createUserWithRole(UserRole::DOCTOR);

        $this->assertTrue(
            $doctor->can('viewAny', MedicalRecord::class)
        );
    }

    public function test_patient_can_view_any_medical_record(): void
    {
        $patient = $this->createUserWithRole(UserRole::PATIENT);

        $this->assertTrue(
            $patient->can('viewAny', MedicalRecord::class)
        );
    }

    public function test_receptionist_cannot_view_any_medical_record(): void
    {
        $receptionist = $this->createUserWithRole(
            UserRole::RECEPTIONIST
        );

        $this->assertFalse(
            $receptionist->can('viewAny', MedicalRecord::class)
        );
    }

    public function test_doctor_can_view_own_medical_record(): void
    {
        $doctorUser = $this->createUserWithRole(UserRole::DOCTOR);
        $patientUser = $this->createUserWithRole(UserRole::PATIENT);

        $doctor = $this->createDoctor($doctorUser);
        $patient = $this->createPatient($patientUser);

        $record = $this->createMedicalRecord($doctor, $patient);

        $this->assertTrue($doctorUser->can('view', $record));
    }

    public function test_doctor_cannot_view_another_doctors_medical_record(): void
    {
        $doctorUser1 = $this->createUserWithRole(UserRole::DOCTOR);
        $doctorUser2 = $this->createUserWithRole(UserRole::DOCTOR);
        $patientUser = $this->createUserWithRole(UserRole::PATIENT);

        $doctor1 = $this->createDoctor($doctorUser1);
        $doctor2 = $this->createDoctor($doctorUser2);
        $patient = $this->createPatient($patientUser);

        $record = $this->createMedicalRecord($doctor2, $patient);

        $this->assertFalse($doctorUser1->can('view', $record));
    }

    public function test_patient_can_view_own_medical_record(): void
    {
        $doctorUser = $this->createUserWithRole(UserRole::DOCTOR);
        $patientUser = $this->createUserWithRole(UserRole::PATIENT);

        $doctor = $this->createDoctor($doctorUser);
        $patient = $this->createPatient($patientUser);

        $record = $this->createMedicalRecord($doctor, $patient);

        $this->assertTrue($patientUser->can('view', $record));
    }

    public function test_patient_cannot_view_another_patients_medical_record(): void
    {
        $doctorUser = $this->createUserWithRole(UserRole::DOCTOR);
        $patientUser1 = $this->createUserWithRole(UserRole::PATIENT);
        $patientUser2 = $this->createUserWithRole(UserRole::PATIENT);

        $doctor = $this->createDoctor($doctorUser);
        $patient1 = $this->createPatient($patientUser1);
        $patient2 = $this->createPatient($patientUser2);

        $record = $this->createMedicalRecord($doctor, $patient2);

        $this->assertFalse($patientUser1->can('view', $record));
    }

    public function test_only_admin_and_doctor_can_create_medical_records(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $doctor = $this->createUserWithRole(UserRole::DOCTOR);
        $patient = $this->createUserWithRole(UserRole::PATIENT);
        $receptionist = $this->createUserWithRole(UserRole::RECEPTIONIST);

        $this->assertTrue($admin->can('create', MedicalRecord::class));
        $this->assertTrue($doctor->can('create', MedicalRecord::class));
        $this->assertFalse($patient->can('create', MedicalRecord::class));
        $this->assertFalse($receptionist->can('create', MedicalRecord::class));
    }

    public function test_only_admin_and_owning_doctor_can_update_medical_record(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $doctorUser = $this->createUserWithRole(UserRole::DOCTOR);
        $anotherDoctorUser = $this->createUserWithRole(UserRole::DOCTOR);
        $patientUser = $this->createUserWithRole(UserRole::PATIENT);

        $doctor = $this->createDoctor($doctorUser);
        $this->createDoctor($anotherDoctorUser);

        $patient = $this->createPatient($patientUser);

        $record = $this->createMedicalRecord($doctor, $patient);

        $this->assertTrue($admin->can('update', $record));
        $this->assertTrue($doctorUser->can('update', $record));
        $this->assertFalse($anotherDoctorUser->can('update', $record));
        $this->assertFalse($patientUser->can('update', $record));
    }

   
public function test_only_admin_can_delete_medical_record(): void
{
    $admin = $this->createUserWithRole(UserRole::ADMIN);
    $doctorUser = $this->createUserWithRole(UserRole::DOCTOR);
    $patientUser = $this->createUserWithRole(UserRole::PATIENT);

    $doctor = $this->createDoctor($doctorUser);
    $patient = $this->createPatient($patientUser);

    $record = $this->createMedicalRecord($doctor, $patient);

    $this->assertTrue($admin->can('delete', $record));
    $this->assertFalse($doctorUser->can('delete', $record));
    $this->assertFalse($patientUser->can('delete', $record));
}

}
