<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceptionistBranchScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_receptionist_only_sees_appointments_from_assigned_branches(): void
    {
        $branch1 = $this->createBranch('BR001');
        $branch2 = $this->createBranch('BR002');

        $appointment1 = $this->createAppointment($branch1, 'APT001');
        $this->createAppointment($branch2, 'APT002');

        $receptionist = User::factory()->create();
        $receptionist->forceFill([
            'role' => UserRole::RECEPTIONIST,
        ])->save();

        $receptionist->branches()->attach($branch1->id);

        $this->actingAs($receptionist);

        $visibleAppointments = Appointment::pluck('id')->all();

        $this->assertEquals(
            [$appointment1->id],
            $visibleAppointments
        );
    }

    public function test_admin_can_see_appointments_from_all_branches(): void
    {
        $branch1 = $this->createBranch('BR003');
        $branch2 = $this->createBranch('BR004');

        $this->createAppointment($branch1, 'APT003');
        $this->createAppointment($branch2, 'APT004');

        $admin = User::factory()->create();
        $admin->forceFill([
            'role' => UserRole::ADMIN,
        ])->save();

        $this->actingAs($admin);

        $this->assertCount(2, Appointment::all());
    }

    public function test_unauthenticated_user_can_query_appointments_without_branch_filter(): void
    {
        $branch1 = $this->createBranch('BR005');
        $branch2 = $this->createBranch('BR006');

        $this->createAppointment($branch1, 'APT005');
        $this->createAppointment($branch2, 'APT006');

        $this->assertCount(2, Appointment::all());
    }

    private function createBranch(string $code): Branch
    {
        return Branch::create([
            'name' => 'Test Branch '.$code,
            'code' => $code,
            'city' => 'Damascus',
            'address' => 'Test Address',
        ]);
    }

    private function createAppointment(
        Branch $branch,
        string $reference
    ): Appointment {
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
    public function test_doctor_only_sees_own_appointments(): void
{
    $branch = $this->createBranch('BR007');

    $ownAppointment = $this->createAppointment($branch, 'APT007');
    $this->createAppointment($branch, 'APT008');

    $doctorUser = $ownAppointment->doctor->user;

    $doctorUser->forceFill([
        'role' => UserRole::DOCTOR,
    ])->save();

    $this->actingAs($doctorUser);

    $this->assertEquals(
        [$ownAppointment->id],
        Appointment::pluck('id')->all()
    );
}
}
