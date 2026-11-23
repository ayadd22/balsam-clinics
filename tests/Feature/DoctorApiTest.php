<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DoctorApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_get_doctors_by_branch(): void
    {
        Sanctum::actingAs($this->createUser());

        $branch = $this->createBranch();

        $doctor = $this->createDoctor(
            $branch,
            'cardiology',
            'Doctor One'
        );

        $response = $this->getJson(
            "/api/doctors?branch_id={$branch->id}"
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items.0.id', $doctor->id)
            ->assertJsonPath('data.items.0.name', 'Doctor One')
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_doctors_can_be_filtered_by_specialty(): void
    {
        Sanctum::actingAs($this->createUser());

        $branch = $this->createBranch();

        $cardiologist = $this->createDoctor(
            $branch,
            'cardiology',
            'Cardiology Doctor'
        );

        $this->createDoctor(
            $branch,
            'dentistry',
            'Dentistry Doctor'
        );

        $response = $this->getJson(
            "/api/doctors?branch_id={$branch->id}&specialty=cardiology"
        );

        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', $cardiologist->id);
    }

    public function test_doctors_endpoint_supports_pagination(): void
    {
        Sanctum::actingAs($this->createUser());

        $branch = $this->createBranch();

        for ($i = 1; $i <= 3; $i++) {
            $this->createDoctor(
                $branch,
                'cardiology',
                "Doctor {$i}"
            );
        }

        $response = $this->getJson(
            "/api/doctors?branch_id={$branch->id}&per_page=2&page=1"
        );

        $response->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.pagination.current_page', 1)
            ->assertJsonPath('data.pagination.per_page', 2)
            ->assertJsonPath('data.pagination.total', 3)
            ->assertJsonPath('data.pagination.last_page', 2);
    }

    public function test_guest_cannot_access_doctors_endpoint(): void
    {
        $branch = $this->createBranch();

        $this->getJson("/api/doctors?branch_id={$branch->id}")
            ->assertUnauthorized();
    }

    private function createUser(): User
    {
        $user = User::factory()->create();

        $user->forceFill([
            'role' => UserRole::ADMIN,
        ])->save();

        return $user;
    }

    private function createBranch(): Branch
    {
        return Branch::create([
            'name' => 'Test Branch',
            'code' => 'BR-' . uniqid(),
            'city' => 'Damascus',
            'address' => 'Test Address',
        ]);
    }

    private function createDoctor(
        Branch $branch,
        string $specialty,
        string $name
    ): Doctor {
        $user = User::factory()->create([
            'name' => $name,
        ]);

        $user->forceFill([
            'role' => UserRole::DOCTOR,
        ])->save();

        $doctor = Doctor::create([
            'user_id' => $user->id,
            'specialty' => $specialty,
            'license_number' => 'LIC-' . uniqid(),
            'is_active' => true,
        ]);

        DoctorSchedule::create([
            'doctor_id' => $doctor->id,
            'branch_id' => $branch->id,
            'day_of_week' => 'monday',
            'start_time' => '09:00:00',
            'end_time' => '15:00:00',
            'is_active' => true,
        ]);

        return $doctor;
    }
}
