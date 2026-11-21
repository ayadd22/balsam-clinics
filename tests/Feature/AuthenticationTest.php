<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_register_successfully(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Aya',
            'email' => 'aya@example.com',
            'phone' => '0991234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Registration successful');

        $this->assertDatabaseHas('users', [
            'email' => 'aya@example.com',
            'role' => 'patient',
        ]);

        $this->assertDatabaseHas('patients', [
            'full_name' => 'Aya',
            'phone' => '0991234567',
        ]);
    }

    public function test_registration_does_not_allow_role_escalation(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Aya',
            'email' => 'aya@example.com',
            'phone' => '0991234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ])->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'aya@example.com',
            'role' => 'patient',
        ]);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create([
            'email' => 'aya@example.com',
        ]);

        $this->postJson('/api/auth/register', [
            'name' => 'Aya',
            'email' => 'aya@example.com',
            'phone' => '0991234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertUnprocessable();
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        User::factory()->create([
            'email' => 'aya@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'aya@example.com',
            'password' => 'password123',
        ])->assertOk()
          ->assertJsonPath('success', true)
          ->assertJsonStructure([
              'data' => [
                  'user',
                  'token',
              ],
          ]);
    }

    public function test_login_fails_with_incorrect_password(): void
    {
        User::factory()->create([
            'email' => 'aya@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'aya@example.com',
            'password' => 'wrongpassword',
        ])->assertUnprocessable();
    }

    
public function test_user_can_logout_and_token_is_revoked(): void
{
    $user = User::factory()->create();

    $token = $user->createToken('test-token')->plainTextToken;

    $tokenId = explode('|', $token)[0];

    $this->withToken($token)
        ->postJson('/api/auth/logout')
        ->assertOk()
        ->assertJsonPath('message', 'Logged out successfully');

    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $tokenId,
    ]);
}

}
