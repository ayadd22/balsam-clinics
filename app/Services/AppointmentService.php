<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AppointmentService
{
    public function create(Patient $patient, array $data): Appointment
    {
        return DB::transaction(function () use ($patient, $data) {

            // Lock patient and doctor to reduce concurrent booking conflicts.
            $patient = Patient::query()
                ->lockForUpdate()
                ->findOrFail($patient->id);

            $doctor = Doctor::query()
                ->lockForUpdate()
                ->findOrFail($data['doctor_id']);

            // 1. Check patient booking restriction.
            if (
                $patient->is_booking_blocked &&
                (
                    !$patient->blocked_until ||
                    $patient->blocked_until->isFuture()
                )
            ) {
                throw ValidationException::withMessages([
                    'patient' => 'This patient is currently blocked from booking appointments.',
                ]);
            }

            // 2. Check doctor and appointment date.
            if (!$doctor->is_active) {
                throw ValidationException::withMessages([
                    'doctor_id' => 'This doctor is not active.',
                ]);
            }

            $date = Carbon::parse($data['appointment_date']);

            if ($date->isPast() && !$date->isToday()) {
                throw ValidationException::withMessages([
                    'appointment_date' => 'Appointments cannot be booked in the past.',
                ]);
            }

            $startTime = $this->normalizeTime($data['start_time']);
            $endTime = $this->normalizeTime($data['end_time']);

            if ($startTime >= $endTime) {
                throw ValidationException::withMessages([
                    'end_time' => 'The end time must be after the start time.',
                ]);
            }

            // 3. Check doctor's schedule at the selected branch and day.
            $dayOfWeek = strtolower($date->format('l'));

            $hasSchedule = DoctorSchedule::query()
                ->where('doctor_id', $doctor->id)
                ->where('branch_id', $data['branch_id'])
                ->where('day_of_week', $dayOfWeek)
                ->where('is_active', true)
                ->where('start_time', '<=', $startTime)
                ->where('end_time', '>=', $endTime)
                ->exists();

            if (!$hasSchedule) {
                throw ValidationException::withMessages([
                    'appointment_date' => 'The doctor is not available at this branch and time.',
                ]);
            }

            // 4. Check doctor overlapping appointments.
            $doctorHasConflict = Appointment::withoutGlobalScopes()
                ->where('doctor_id', $doctor->id)
                ->whereDate('appointment_date', $date->toDateString())
                ->where('status', '!=', AppointmentStatus::CANCELLED->value)
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime)
                ->exists();

            if ($doctorHasConflict) {
                throw ValidationException::withMessages([
                    'start_time' => 'The doctor already has an appointment during this time.',
                ]);
            }

            // 5. Check patient overlapping appointments.
            $patientHasConflict = Appointment::withoutGlobalScopes()
                ->where('patient_id', $patient->id)
                ->whereDate('appointment_date', $date->toDateString())
                ->where('status', '!=', AppointmentStatus::CANCELLED->value)
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime)
                ->exists();

            if ($patientHasConflict) {
                throw ValidationException::withMessages([
                    'start_time' => 'The patient already has an appointment during this time.',
                ]);
            }

            // 6. Create appointment.
            return Appointment::create([
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'branch_id' => $data['branch_id'],
                'appointment_date' => $date->toDateString(),
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => AppointmentStatus::BOOKED,
                'reference_number' => 'APT-' . Str::upper(Str::random(12)),
            ]);
        });
    }

    private function normalizeTime(string $time): string
    {
        return Carbon::parse($time)->format('H:i:s');
    }
}
