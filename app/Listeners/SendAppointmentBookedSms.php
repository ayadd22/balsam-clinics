<?php

namespace App\Listeners;

use App\Contracts\SmsServiceInterface;
use App\Events\AppointmentBooked;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendAppointmentBookedSms implements ShouldQueue
{
    public string $queue = 'high';

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(
        private SmsServiceInterface $smsService
    ) {}

    public function handle(AppointmentBooked $event): void
    {
        $event->appointment->loadMissing('patient');

        $this->smsService->sendAppointmentBooked(
            $event->appointment
        );
    }
}