<?php

namespace Tests\Feature;

use App\Contracts\SmsServiceInterface;
use App\Events\AppointmentBooked;
use App\Events\AppointmentCancelled;
use App\Listeners\SendAppointmentBookedSms;
use App\Listeners\SendAppointmentCancelledSms;
use App\Models\Appointment;
use App\Models\Patient;
use App\Services\LogSmsService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class SmsServiceTest extends TestCase
{
    private function makeAppointment(): Appointment
    {
        $patient = new Patient();
        $patient->phone = '0999999999';

        $appointment = new Appointment();
        $appointment->reference_number = 'APT-123';
        $appointment->setRelation('patient', $patient);

        return $appointment;
    }

    public function test_sms_interface_is_bound_to_log_sms_service(): void
    {
        $service = app(SmsServiceInterface::class);

        $this->assertInstanceOf(LogSmsService::class, $service);
    }

    public function test_log_sms_service_logs_booking_confirmation(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with(
                'Appointment booking confirmation SMS',
                Mockery::on(fn ($context) =>
                    $context['phone'] === '0999999999'
                    && $context['reference_number'] === 'APT-123'
                )
            );

        app(LogSmsService::class)
            ->sendAppointmentBooked($this->makeAppointment());
    }

    public function test_log_sms_service_logs_cancellation(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with(
                'Appointment cancellation SMS',
                Mockery::on(fn ($context) =>
                    $context['phone'] === '0999999999'
                    && $context['reference_number'] === 'APT-123'
                )
            );

        app(LogSmsService::class)
            ->sendAppointmentCancelled($this->makeAppointment());
    }

    public function test_booking_listener_calls_sms_service(): void
    {
        $appointment = $this->makeAppointment();

        $smsService = Mockery::mock(SmsServiceInterface::class);

        $smsService->shouldReceive('sendAppointmentBooked')
            ->once()
            ->with($appointment);

        $listener = new SendAppointmentBookedSms($smsService);

        $listener->handle(new AppointmentBooked($appointment));
    }

    public function test_cancellation_listener_calls_sms_service(): void
    {
        $appointment = $this->makeAppointment();

        $smsService = Mockery::mock(SmsServiceInterface::class);

        $smsService->shouldReceive('sendAppointmentCancelled')
            ->once()
            ->with($appointment);

        $listener = new SendAppointmentCancelledSms($smsService);

        $listener->handle(new AppointmentCancelled($appointment));
    }

    public function test_sms_listeners_use_high_priority_queue_and_retry_settings(): void
    {
        $smsService = Mockery::mock(SmsServiceInterface::class);

        $listeners = [
            new SendAppointmentBookedSms($smsService),
            new SendAppointmentCancelledSms($smsService),
        ];

        foreach ($listeners as $listener) {
            $this->assertSame('high', $listener->queue);
            $this->assertSame(3, $listener->tries);
            $this->assertSame([10, 30, 60], $listener->backoff);
        }
    }
}
