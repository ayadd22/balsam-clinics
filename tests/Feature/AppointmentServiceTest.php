<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\User;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AppointmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createBookingContext(): array
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
            'license_number' => 'DOC-001',
            'is_active' => true,
        ]);

        $patient = Patient::create([
            'full_name' => 'Test Patient',
            'phone' => '0999999999',
        ]);

        DoctorSchedule::create([
            'doctor_id' => $doctor->id,
            'branch_id' => $branch->id,
            'day_of_week' => 'monday',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_active' => true,
        ]);

        return compact('branch', 'doctor', 'patient', 'date');
    }

    private function bookingData(array $context): array
    {
        return [
            'doctor_id' => $context['doctor']->id,
            'branch_id' => $context['branch']->id,
            'appointment_date' => $context['date']->toDateString(),
            'start_time' => '10:00',
            'end_time' => '10:30',
        ];
    }

    private function createExistingAppointment(
        array $context,
        string $reference,
        string $status = 'booked',
        ?int $patientId = null
    ): Appointment {
        return Appointment::create([
            'patient_id' => $patientId ?? $context['patient']->id,
            'doctor_id' => $context['doctor']->id,
            'branch_id' => $context['branch']->id,
            'appointment_date' => $context['date']->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'status' => $status,
            'reference_number' => $reference,
        ]);
    }

    public function test_appointment_can_be_created_successfully(): void
    {
        $context = $this->createBookingContext();

        $appointment = app(AppointmentService::class)->create(
            $context['patient'],
            $this->bookingData($context)
        );

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'patient_id' => $context['patient']->id,
            'doctor_id' => $context['doctor']->id,
            'branch_id' => $context['branch']->id,
            'status' => 'booked',
        ]);

        $this->assertNotEmpty($appointment->reference_number);
    }

    public function test_appointment_outside_doctor_schedule_is_rejected(): void
    {
        $context = $this->createBookingContext();

        $data = $this->bookingData($context);
        $data['start_time'] = '08:00';
        $data['end_time'] = '08:30';

        try {
            app(AppointmentService::class)->create(
                $context['patient'],
                $data
            );

            $this->fail('Appointment outside schedule should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'appointment_date',
                $exception->errors()
            );
        }

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_doctor_cannot_be_booked_at_branch_without_schedule(): void
    {
        $context = $this->createBookingContext();

        $otherBranch = Branch::create([
            'name' => 'Other Branch',
            'code' => 'OTHER',
            'city' => 'Damascus',
            'address' => 'Other Address',
            'is_active' => true,
        ]);

        $data = $this->bookingData($context);
        $data['branch_id'] = $otherBranch->id;

        try {
            app(AppointmentService::class)->create(
                $context['patient'],
                $data
            );

            $this->fail('Booking at an unavailable branch should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'appointment_date',
                $exception->errors()
            );
        }

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_blocked_patient_cannot_book_appointment(): void
    {
        $context = $this->createBookingContext();

        $context['patient']->update([
            'is_booking_blocked' => true,
            'blocked_until' => null,
        ]);

        try {
            app(AppointmentService::class)->create(
                $context['patient'],
                $this->bookingData($context)
            );

            $this->fail('Blocked patient should not be allowed to book.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('patient', $exception->errors());
        }

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_doctor_cannot_have_overlapping_appointments(): void
    {
        $context = $this->createBookingContext();

        $this->createExistingAppointment(
            $context,
            'EXIST-001'
        );

        $data = $this->bookingData($context);
        $data['start_time'] = '10:15';
        $data['end_time'] = '10:45';

        try {
            app(AppointmentService::class)->create(
                $context['patient'],
                $data
            );

            $this->fail('Overlapping doctor appointment should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('start_time', $exception->errors());
        }

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_patient_cannot_have_overlapping_appointments(): void
    {
        $context = $this->createBookingContext();

        $otherDoctorUser = User::factory()->create();

        $otherDoctor = Doctor::create([
            'user_id' => $otherDoctorUser->id,
            'specialty' => 'cardiology',
            'license_number' => 'DOC-002',
            'is_active' => true,
        ]);

        DoctorSchedule::create([
            'doctor_id' => $otherDoctor->id,
            'branch_id' => $context['branch']->id,
            'day_of_week' => 'monday',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_active' => true,
        ]);

        $this->createExistingAppointment(
            array_merge($context, ['doctor' => $otherDoctor]),
            'EXIST-002'
        );

        $data = $this->bookingData($context);
        $data['start_time'] = '10:15';
        $data['end_time'] = '10:45';

        try {
            app(AppointmentService::class)->create(
                $context['patient'],
                $data
            );

            $this->fail('Overlapping patient appointment should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('start_time', $exception->errors());
        }

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_cancelled_appointment_does_not_prevent_new_booking(): void
    {
        $context = $this->createBookingContext();

        $this->createExistingAppointment(
            $context,
            'CANCEL-001',
            AppointmentStatus::CANCELLED->value
        );

        $appointment = app(AppointmentService::class)->create(
            $context['patient'],
            $this->bookingData($context)
        );

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'booked',
        ]);

        $this->assertDatabaseCount('appointments', 2);
    }
}
