<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\User;
use App\Services\AppointmentAvailabilityService;
use App\Services\DoctorCacheService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheInvalidationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);

        Cache::flush();
    }

    private function createContext(): array
    {
        $date = Carbon::now()->addWeeks(2)->next(Carbon::MONDAY);

        $branch = Branch::create([
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'city' => 'Damascus',
            'address' => 'Test Address',
            'is_active' => true,
        ]);

        $doctorUser = User::factory()->create();

        $doctor = Doctor::create([
            'user_id' => $doctorUser->id,
            'specialty' => 'general_medicine',
            'license_number' => 'DOC-' . uniqid(),
            'is_active' => true,
        ]);

        $patient = Patient::create([
            'full_name' => 'Test Patient',
            'phone' => '0999999999',
        ]);

        $schedule = DoctorSchedule::create([
            'doctor_id' => $doctor->id,
            'branch_id' => $branch->id,
            'day_of_week' => 'monday',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_active' => true,
        ]);

        return compact(
            'branch',
            'doctor',
            'patient',
            'schedule',
            'date'
        );
    }

    private function checkAvailability(array $context): bool
    {
        return app(AppointmentAvailabilityService::class)->isAvailable(
            $context['doctor']->id,
            $context['branch']->id,
            $context['date']->toDateString(),
            '10:00:00',
            '10:30:00'
        );
    }

    public function test_creating_appointment_invalidates_availability_cache(): void
    {
        $context = $this->createContext();

        // Cache the available result first.
        $this->assertTrue($this->checkAvailability($context));

        // Create an overlapping appointment.
        Appointment::create([
            'patient_id' => $context['patient']->id,
            'doctor_id' => $context['doctor']->id,
            'branch_id' => $context['branch']->id,
            'appointment_date' => $context['date']->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'status' => 'booked',
            'reference_number' => 'APT-' . uniqid(),
        ]);

        // The old cached result must no longer be returned.
        $this->assertFalse($this->checkAvailability($context));
    }

    public function test_updating_schedule_invalidates_availability_cache(): void
    {
        $context = $this->createContext();

        $this->assertTrue($this->checkAvailability($context));

        $context['schedule']->update([
            'is_active' => false,
        ]);

        $this->assertFalse($this->checkAvailability($context));
    }

    public function test_updating_doctor_invalidates_doctors_cache(): void
    {
        $context = $this->createContext();

        $cacheService = app(DoctorCacheService::class);

        // Cache the doctor list.
        $doctors = $cacheService->getDoctors(
            $context['branch']->id
        );

        $this->assertSame(1, $doctors->total());

        // Deactivate the doctor.
        $context['doctor']->update([
            'is_active' => false,
        ]);

        // The cached list must be refreshed.
        $doctors = $cacheService->getDoctors(
            $context['branch']->id
        );

        $this->assertSame(0, $doctors->total());
    }
}
