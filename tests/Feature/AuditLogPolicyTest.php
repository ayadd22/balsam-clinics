<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_audit_logs(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
        ]);

        $this->assertTrue(
            $admin->can('viewAny', AuditLog::class)
        );

        $this->assertTrue(
            $admin->can('view', new AuditLog())
        );
    }

    public function test_patient_cannot_view_audit_logs(): void
    {
        $patient = User::factory()->create([
            'role' => UserRole::PATIENT,
        ]);

        $this->assertFalse(
            $patient->can('viewAny', AuditLog::class)
        );

        $this->assertFalse(
            $patient->can('view', new AuditLog())
        );
    }

    public function test_doctor_cannot_view_audit_logs(): void
    {
        $doctor = User::factory()->create([
            'role' => UserRole::DOCTOR,
        ]);

        $this->assertFalse(
            $doctor->can('viewAny', AuditLog::class)
        );
    }

    public function test_receptionist_cannot_view_audit_logs(): void
    {
        $receptionist = User::factory()->create([
            'role' => UserRole::RECEPTIONIST,
        ]);

        $this->assertFalse(
            $receptionist->can('viewAny', AuditLog::class)
        );
    }
}
