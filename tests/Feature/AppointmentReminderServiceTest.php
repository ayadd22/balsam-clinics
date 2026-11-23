<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Jobs\SendAppointmentReminderJob;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\User;
use App\Services\AppointmentReminderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use App\Contracts\SmsServiceInterface;

class AppointmentReminderServiceTest extends TestCase
{
    use DatabaseMigrations;

    private function createAppointmentContext(): array
    {
        $date = Carbon::now()->addWeeks(2)->next(Carbon::MONDAY);

        $branch = Branch::create([
            'name' => 'Main Branch',
            'code' => 'BR-' . strtoupper(uniqid()),
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

        DoctorSchedule::create([
            'doctor_id' => $doctor->id,
            'branch_id' => $branch->id,
            'day_of_week' => 'monday',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_active' => true,
        ]);

        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'branch_id' => $branch->id,
            'appointment_date' => $date->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'status' => AppointmentStatus::BOOKED,
            'reference_number' => 'APT-' . uniqid(),
        ]);

        return compact('date', 'branch', 'patient', 'appointment');
    }

    public function test_dry_run_does_not_create_reminders_or_dispatch_jobs(): void
    {
        Queue::fake();

        $context = $this->createAppointmentContext();

        $result = app(AppointmentReminderService::class)->sendReminders(
            $context['date']->toDateString(),
            null,
            true
        );

        $this->assertSame(1, $result['total']);
        $this->assertSame(1, $result['created']);
        $this->assertTrue($result['dry_run']);

        $this->assertDatabaseCount('appointment_reminders', 0);

        Queue::assertNotPushed(SendAppointmentReminderJob::class);
    }

    public function test_reminder_is_created_and_job_is_dispatched_only_once(): void
    {
        Queue::fake();

        $context = $this->createAppointmentContext();

        $service = app(AppointmentReminderService::class);

        $firstResult = $service->sendReminders(
            $context['date']->toDateString()
        );

        $secondResult = $service->sendReminders(
            $context['date']->toDateString()
        );

        $this->assertSame(1, $firstResult['created']);
        $this->assertSame(1, $secondResult['skipped']);

        $this->assertDatabaseCount('appointment_reminders', 1);

        Queue::assertPushed(
            SendAppointmentReminderJob::class,
            1
        );
    }

    public function test_branch_filter_only_processes_appointments_for_selected_branch(): void
    {
        Queue::fake();

        $first = $this->createAppointmentContext();
        $second = $this->createAppointmentContext();

        $result = app(AppointmentReminderService::class)->sendReminders(
            $first['date']->toDateString(),
            $first['branch']->id
        );

        $this->assertSame(1, $result['total']);
        $this->assertDatabaseHas('appointment_reminders', [
            'appointment_id' => $first['appointment']->id,
            'reminder_type' => 'day_before',
        ]);

        $this->assertDatabaseMissing('appointment_reminders', [
            'appointment_id' => $second['appointment']->id,
        ]);
    }
    public function test_job_sends_sms_and_marks_reminder_as_sent(): void
{
    $context = $this->createAppointmentContext();

    $reminder = AppointmentReminder::create([
        'appointment_id' => $context['appointment']->id,
        'reminder_type' => 'day_before',
        'status' => 'pending',
    ]);

    $smsService = \Mockery::mock(SmsServiceInterface::class);

    $smsService->shouldReceive('sendAppointmentReminder')
        ->once()
        ->with(\Mockery::type(Appointment::class));

    (new SendAppointmentReminderJob($reminder->id))
        ->handle($smsService);

    $this->assertDatabaseHas('appointment_reminders', [
        'id' => $reminder->id,
        'status' => 'sent',
    ]);

    $this->assertNotNull($reminder->fresh()->sent_at);
}

public function test_job_does_not_send_sms_again_if_reminder_is_already_sent(): void
{
    $context = $this->createAppointmentContext();

    $reminder = AppointmentReminder::create([
        'appointment_id' => $context['appointment']->id,
        'reminder_type' => 'day_before',
        'status' => 'sent',
        'sent_at' => now(),
    ]);

    $smsService = \Mockery::mock(SmsServiceInterface::class);

    $smsService->shouldNotReceive('sendAppointmentReminder');

    (new SendAppointmentReminderJob($reminder->id))
        ->handle($smsService);
}

public function test_failed_job_marks_pending_reminder_as_failed(): void
{
    $context = $this->createAppointmentContext();

    $reminder = AppointmentReminder::create([
        'appointment_id' => $context['appointment']->id,
        'reminder_type' => 'day_before',
        'status' => 'pending',
    ]);

    (new SendAppointmentReminderJob($reminder->id))
        ->failed(new \RuntimeException('SMS provider failed'));

    $this->assertDatabaseHas('appointment_reminders', [
        'id' => $reminder->id,
        'status' => 'failed',
    ]);
}
}
