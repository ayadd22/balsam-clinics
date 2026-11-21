<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Doctor;
use App\Models\Appointment;
use App\Models\DoctorSchedule;
use App\Enums\UserRole;
use App\Enums\Specialty;
use App\Enums\DayOfWeek;
use App\Enums\AppointmentStatus;

class ModelCastsTest extends TestCase
{
    public function test_models_cast_enum_attributes_correctly(): void
    {
        $user = new User();
        $user->role = 'admin';

        $this->assertSame(UserRole::ADMIN, $user->role);

        $doctor = new Doctor();
        $doctor->specialty = 'cardiology';

        $this->assertSame(Specialty::CARDIOLOGY, $doctor->specialty);

        $schedule = new DoctorSchedule();
        $schedule->day_of_week = 'monday';

        $this->assertSame(DayOfWeek::MONDAY, $schedule->day_of_week);

        $appointment = new Appointment();
        $appointment->status = 'booked';

        $this->assertSame(AppointmentStatus::BOOKED, $appointment->status);
    }
}
