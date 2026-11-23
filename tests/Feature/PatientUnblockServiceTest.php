<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Services\PatientUnblockService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class PatientUnblockServiceTest extends TestCase
{
    use DatabaseMigrations;

    public function test_expired_blocked_patient_is_unblocked(): void
    {
        Carbon::setTestNow('2026-11-23 16:00:00');

        $patient = Patient::create([
            'full_name' => 'Expired Patient',
            'phone' => '0991111111',
            'is_booking_blocked' => true,
            'blocked_until' => now()->subDay(),
        ]);

        $result = app(PatientUnblockService::class)
            ->unblockExpiredPatients();

        $this->assertSame(1, $result['total']);
        $this->assertSame(1, $result['updated']);

        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'is_booking_blocked' => false,
            'blocked_until' => null,
        ]);

        Carbon::setTestNow();
    }

    public function test_patient_with_future_block_expiration_remains_blocked(): void
    {
        Carbon::setTestNow('2026-11-23 16:00:00');

        $patient = Patient::create([
            'full_name' => 'Future Patient',
            'phone' => '0992222222',
            'is_booking_blocked' => true,
            'blocked_until' => now()->addDay(),
        ]);

        $result = app(PatientUnblockService::class)
            ->unblockExpiredPatients();

        $this->assertSame(0, $result['total']);

        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'is_booking_blocked' => true,
        ]);

        Carbon::setTestNow();
    }

    public function test_patient_without_expiration_date_remains_blocked(): void
    {
        $patient = Patient::create([
            'full_name' => 'Permanent Block Patient',
            'phone' => '0993333333',
            'is_booking_blocked' => true,
            'blocked_until' => null,
        ]);

        $result = app(PatientUnblockService::class)
            ->unblockExpiredPatients();

        $this->assertSame(0, $result['total']);

        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'is_booking_blocked' => true,
            'blocked_until' => null,
        ]);
    }

    public function test_dry_run_does_not_unblock_patients(): void
    {
        Carbon::setTestNow('2026-11-23 16:00:00');

        $patient = Patient::create([
            'full_name' => 'Dry Run Patient',
            'phone' => '0994444444',
            'is_booking_blocked' => true,
            'blocked_until' => now()->subDay(),
        ]);

        $result = app(PatientUnblockService::class)
            ->unblockExpiredPatients(true);

        $this->assertSame(1, $result['total']);
        $this->assertSame(0, $result['updated']);
        $this->assertTrue($result['dry_run']);

        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'is_booking_blocked' => true,
        ]);

        Carbon::setTestNow();
    }
}
