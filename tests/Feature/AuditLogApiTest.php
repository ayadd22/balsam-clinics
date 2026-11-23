<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAuditLog(): AuditLog
    {
        $user = User::factory()->create([
            'role' => UserRole::ADMIN,
        ]);

        $auditLog = new AuditLog();

        $auditLog->forceFill([
            'user_id' => $user->id,
            'event' => 'created',
            'auditable_type' => 'App\\Models\\Appointment',
            'auditable_id' => 1,
            'old_values' => null,
            'new_values' => [
                'status' => 'booked',
            ],
        ]);

        $auditLog->save();

        return $auditLog;
    }

    public function test_admin_can_view_audit_logs(): void
    {
        $this->createAuditLog();

        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/audit-logs')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_view_single_audit_log(): void
    {
        $auditLog = $this->createAuditLog();

        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson("/api/audit-logs/{$auditLog->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $auditLog->id)
            ->assertJsonPath('data.event', 'created');
    }

    public function test_receptionist_cannot_view_audit_logs(): void
    {
        $receptionist = User::factory()->create([
            'role' => UserRole::RECEPTIONIST,
        ]);

        Sanctum::actingAs($receptionist);

        $this->getJson('/api/audit-logs')
            ->assertForbidden();
    }

    public function test_doctor_cannot_view_audit_logs(): void
    {
        $doctor = User::factory()->create([
            'role' => UserRole::DOCTOR,
        ]);

        Sanctum::actingAs($doctor);

        $this->getJson('/api/audit-logs')
            ->assertForbidden();
    }

    public function test_patient_cannot_view_audit_logs(): void
    {
        $patient = User::factory()->create([
            'role' => UserRole::PATIENT,
        ]);

        Sanctum::actingAs($patient);

        $this->getJson('/api/audit-logs')
            ->assertForbidden();
    }

    public function test_guest_cannot_view_audit_logs(): void
    {
        $this->getJson('/api/audit-logs')
            ->assertUnauthorized();
    }

    public function test_audit_logs_cannot_be_updated_through_api(): void
    {
        $auditLog = $this->createAuditLog();

        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
        ]);

        Sanctum::actingAs($admin);

        $this->putJson("/api/audit-logs/{$auditLog->id}", [
            'event' => 'updated',
        ])->assertMethodNotAllowed();

        $this->assertDatabaseHas('audit_logs', [
            'id' => $auditLog->id,
            'event' => 'created',
        ]);
    }

    public function test_audit_logs_cannot_be_deleted_through_api(): void
    {
        $auditLog = $this->createAuditLog();

        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
        ]);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/audit-logs/{$auditLog->id}")
            ->assertMethodNotAllowed();

        $this->assertDatabaseHas('audit_logs', [
            'id' => $auditLog->id,
        ]);
    }

    public function test_audit_log_model_cannot_be_updated(): void
    {
        $auditLog = $this->createAuditLog();

        $this->expectException(\LogicException::class);

        $auditLog->update([
            'event' => 'updated',
        ]);
    }

    public function test_audit_log_model_cannot_be_deleted(): void
    {
        $auditLog = $this->createAuditLog();

        $this->expectException(\LogicException::class);

        $auditLog->delete();
    }
}
