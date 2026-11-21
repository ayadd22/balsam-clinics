<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorSchedulePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_view_own_schedule(): void
    {
        [$doctorUser, $schedule] = $this->createDoctorSchedule();

        $this->assertTrue($doctorUser->can('view', $schedule));
    }

    public function test_doctor_cannot_view_another_doctor_schedule(): void
    {
        [, $schedule] = $this->createDoctorSchedule();

        $otherDoctor = User::factory()->create();
        $otherDoctor->forceFill([
            'role' => UserRole::DOCTOR,
        ])->save();

        Doctor::create([
            'user_id' => $otherDoctor->id,
            'specialty' => 'cardiology',
            'license_number' => 'LIC-OTHER',
        ]);

        $this->assertFalse($otherDoctor->can('view', $schedule));
    }

    public function test_admin_can_manage_schedules(): void
    {
        [, $schedule] = $this->createDoctorSchedule();

        $admin = User::factory()->create();
        $admin->forceFill([
            'role' => UserRole::ADMIN,
        ])->save();

        $this->assertTrue($admin->can('view', $schedule));
        $this->assertTrue($admin->can('create', DoctorSchedule::class));
        $this->assertTrue($admin->can('update', $schedule));
        $this->assertTrue($admin->can('delete', $schedule));
    }

    public function test_receptionist_can_view_schedules_in_assigned_branches(): void
    {
        [$doctorUser, $schedule, $branch] = $this->createDoctorSchedule();

        $receptionist = User::factory()->create();
        $receptionist->forceFill([
            'role' => UserRole::RECEPTIONIST,
        ])->save();

        $receptionist->branches()->attach($branch->id);

        $this->assertTrue($receptionist->can('view', $schedule));
    }

    public function test_receptionist_cannot_view_schedules_in_other_branches(): void
    {
        [, $schedule] = $this->createDoctorSchedule();

        $receptionist = User::factory()->create();
        $receptionist->forceFill([
            'role' => UserRole::RECEPTIONIST,
        ])->save();

        $this->assertFalse($receptionist->can('view', $schedule));
    }

    public function test_doctor_cannot_manage_schedules(): void
    {
        [$doctorUser, $schedule] = $this->createDoctorSchedule();

        $this->assertFalse($doctorUser->can('create', DoctorSchedule::class));
        $this->assertFalse($doctorUser->can('update', $schedule));
        $this->assertFalse($doctorUser->can('delete', $schedule));
    }

    private function createDoctorSchedule(): array
    {
        $branch = Branch::create([
            'name' => 'Test Branch',
            'code' => 'BR-'.uniqid(),
            'city' => 'Damascus',
            'address' => 'Test Address',
        ]);

        $doctorUser = User::factory()->create();
        $doctorUser->forceFill([
            'role' => UserRole::DOCTOR,
        ])->save();

        $doctor = Doctor::create([
            'user_id' => $doctorUser->id,
            'specialty' => 'cardiology',
            'license_number' => 'LIC-'.uniqid(),
        ]);

        $schedule = DoctorSchedule::create([
            'doctor_id' => $doctor->id,
            'branch_id' => $branch->id,
            'day_of_week' => 'monday',
            'start_time' => '09:00:00',
            'end_time' => '15:00:00',
            'is_active' => true,
        ]);

        return [$doctorUser, $schedule, $branch];
    }
}
