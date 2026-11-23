<?php

namespace Tests\Feature;

use App\Events\AppointmentBooked;
use App\Listeners\SendAppointmentBookedSms;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\User;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use RuntimeException;
use Illuminate\Support\Facades\Event;
use App\Events\AppointmentCancelled;
use App\Listeners\SendAppointmentCancelledSms;




class AppointmentAfterCommitTest extends TestCase
{
    use DatabaseMigrations;

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

    public function test_booking_listener_is_queued_after_transaction_commits(): void
    {
        Queue::fake();

        $context = $this->createBookingContext();

        $appointment = app(AppointmentService::class)->create(
            $context['patient'],
            $this->bookingData($context)
        );
        

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'booked',
        ]);

        Queue::assertPushed(
    CallQueuedListener::class,
    function (CallQueuedListener $job) {
        return $job->class === SendAppointmentBookedSms::class;
    }
);


    }

    public function test_booking_listener_is_not_queued_when_transaction_rolls_back(): void
    {
        Queue::fake();

        $context = $this->createBookingContext();

        try {
            DB::transaction(function () use ($context) {
                app(AppointmentService::class)->create(
                    $context['patient'],
                    $this->bookingData($context)
                );

                throw new RuntimeException('Force transaction rollback.');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Force transaction rollback.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseCount('appointments', 0);

        Queue::assertNotPushed(CallQueuedListener::class);
    }
    public function test_cancellation_listener_is_queued_after_transaction_commits(): void
{
    Queue::fake();

    $context = $this->createBookingContext();

    $appointment = app(AppointmentService::class)->create(
        $context['patient'],
        $this->bookingData($context)
    );

    $cancelledAppointment = app(AppointmentService::class)
        ->cancel($appointment);

    $this->assertSame(
        'cancelled',
        $cancelledAppointment->fresh()->status->value
    );

    Queue::assertPushed(
        CallQueuedListener::class,
        function (CallQueuedListener $job) {
            return $job->class === SendAppointmentCancelledSms::class;
        }
    );
}

public function test_cancellation_listener_is_not_queued_when_transaction_rolls_back(): void
{
    Queue::fake();

    $context = $this->createBookingContext();

    $appointment = app(AppointmentService::class)->create(
        $context['patient'],
        $this->bookingData($context)
    );

    try {
        DB::transaction(function () use ($appointment) {
            app(AppointmentService::class)->cancel($appointment);

            throw new RuntimeException('Force transaction rollback.');
        });
    } catch (RuntimeException $exception) {
        $this->assertSame(
            'Force transaction rollback.',
            $exception->getMessage()
        );
    }

    $this->assertSame(
        'booked',
        $appointment->fresh()->status->value
    );

    Queue::assertNotPushed(
        CallQueuedListener::class,
        function (CallQueuedListener $job) {
            return $job->class === SendAppointmentCancelledSms::class;
        }
    );
}

    
    
}
