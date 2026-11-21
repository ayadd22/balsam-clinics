<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'role:admin'])
            ->get('/test-admin-role', fn () => response()->json([
                'message' => 'Access granted',
            ]));
    }

    public function test_admin_can_access_admin_route(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::ADMIN,
        ]);

        $this->actingAs($user)
            ->getJson('/test-admin-role')
            ->assertOk()
            ->assertJson([
                'message' => 'Access granted',
            ]);
    }

    public function test_patient_cannot_access_admin_route(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::PATIENT,
        ]);

        $this->actingAs($user)
            ->getJson('/test-admin-role')
            ->assertForbidden();
    }
}