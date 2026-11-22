<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_appointment_creation_is_audited(): void
    {
        $appointment = $this->createAppointment('AUD001');

        $log = $appointment->auditLogs()
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($log);
        $this->assertNull($log->old_values);
        $this->assertNotEmpty($log->new_values);
        $this->assertEquals(
            'AUD001',
            $log->new_values['reference_number']
        );
    }

    public function test_appointment_update_records_old_and_new_values(): void
    {
        $appointment = $this->createAppointment('OLD-001');

        $appointment->update([
            'reference_number' => 'NEW-001',
        ]);

        $log = $appointment->auditLogs()
            ->where('event', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);

        $this->assertEquals(
            'OLD-001',
            $log->old_values['reference_number']
        );

        $this->assertEquals(
            'NEW-001',
            $log->new_values['reference_number']
        );
    }

    public function test_medical_record_creation_is_audited(): void
    {
        $appointment = $this->createAppointment('AUD002');

        $medicalRecord = MedicalRecord::create([
            'appointment_id' => $appointment->id,
            'doctor_id' => $appointment->doctor_id,
            'diagnosis' => 'Test diagnosis',
            'prescription' => 'Test prescription',
            'notes' => 'Test notes',
        ]);

        $log = $medicalRecord->auditLogs()
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals(
            'Test diagnosis',
            $log->new_values['diagnosis']
        );
    }

    private function createAppointment(string $reference): Appointment
    {
        $branch = Branch::create([
            'name' => 'Test Branch '.$reference,
            'code' => $reference,
            'city' => 'Damascus',
            'address' => 'Test Address',
        ]);

        $doctorUser = User::factory()->create();

        $doctor = Doctor::create([
            'user_id' => $doctorUser->id,
            'specialty' => 'general_medicine',
            'license_number' => 'LIC-'.$reference,
        ]);

        $patient = Patient::create([
            'full_name' => 'Test Patient',
            'phone' => '0999999999',
        ]);

        return Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'branch_id' => $branch->id,
            'appointment_date' => '2026-12-01',
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'reference_number' => $reference,
        ]);
    }

public function test_medical_record_deletion_is_audited(): void
{
    $appointment = $this->createAppointment('AUD003');

    $medicalRecord = MedicalRecord::create([
        'appointment_id' => $appointment->id,
        'doctor_id' => $appointment->doctor_id,
        'diagnosis' => 'Old diagnosis',
    ]);

    $medicalRecord->delete();

    $this->assertDatabaseHas('audit_logs', [
        'auditable_id' => $medicalRecord->id,
        'auditable_type' => MedicalRecord::class,
        'event' => 'deleted',
    ]);

    $log = $medicalRecord->auditLogs()
        ->where('event', 'deleted')
        ->first();

    $this->assertEquals(
        'Old diagnosis',
        $log->old_values['diagnosis']
    );
}

public function test_audit_log_records_authenticated_user(): void
{
    $user = User::factory()->create();

    $this->actingAs($user);

    $appointment = $this->createAppointment('AUD004');

    $log = $appointment->auditLogs()
        ->where('event', 'created')
        ->first();

    $this->assertEquals($user->id, $log->user_id);
}

public function test_audit_log_cannot_be_updated(): void
{
    $appointment = $this->createAppointment('IMM001');

    $log = $appointment->auditLogs()->firstOrFail();

    try {
        $log->update([
            'event' => 'tampered',
        ]);

        $this->fail('Audit log update should be prevented.');
    } catch (\LogicException $exception) {
        $this->assertEquals(
            'Audit logs cannot be modified.',
            $exception->getMessage()
        );
    }

    $this->assertDatabaseHas('audit_logs', [
        'id' => $log->id,
        'event' => 'created',
    ]);
}

public function test_audit_log_cannot_be_deleted(): void
{
    $appointment = $this->createAppointment('IMM002');

    $log = $appointment->auditLogs()->firstOrFail();

    try {
        $log->delete();

        $this->fail('Audit log deletion should be prevented.');
    } catch (\LogicException $exception) {
        $this->assertEquals(
            'Audit logs cannot be deleted.',
            $exception->getMessage()
        );
    }

    $this->assertDatabaseHas('audit_logs', [
        'id' => $log->id,
    ]);
}

}
