<?php

namespace App\Contracts;

use App\Models\Appointment;

interface SmsServiceInterface
{
    public function sendAppointmentBooked(Appointment $appointment): void;

    public function sendAppointmentCancelled(Appointment $appointment): void;
}
