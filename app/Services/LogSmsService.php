<?php

namespace App\Services;

use App\Contracts\SmsServiceInterface;
use App\Models\Appointment;
use Illuminate\Support\Facades\Log;

class LogSmsService implements SmsServiceInterface
{
    public function sendAppointmentBooked(Appointment $appointment): void
    {
        Log::info('Appointment booking confirmation SMS', [
            'phone' => $appointment->patient->phone,
            'reference_number' => $appointment->reference_number,
            'message' => 'Your appointment has been booked successfully.',
        ]);
    }

    public function sendAppointmentCancelled(Appointment $appointment): void
    {
        Log::info('Appointment cancellation SMS', [
            'phone' => $appointment->patient->phone,
            'reference_number' => $appointment->reference_number,
            'message' => 'Your appointment has been cancelled.',
        ]);
    }
}
