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
use App\Events\AppointmentBooked;
use App\Events\AppointmentCancelled;

class AppointmentService
{
    public function create(Patient $patient, array $data): Appointment
    {
        return DB::transaction(function () use ($patient, $data) {

         
            $patient = Patient::query()
                ->lockForUpdate()
                ->findOrFail($patient->id);

            $doctor = Doctor::query()
                ->lockForUpdate()
                ->findOrFail($data['doctor_id']);

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

       
            $appointment = Appointment::create([
               'patient_id' => $patient->id,
               'doctor_id' => $doctor->id,
               'branch_id' => $data['branch_id'],
               'appointment_date' => $date->toDateString(),
               'start_time' => $startTime,
               'end_time' => $endTime,
               'status' => AppointmentStatus::BOOKED,
               'reference_number' => 'APT-' . Str::upper(Str::random(12)),
            ]);

             AppointmentBooked::dispatch($appointment);

             return $appointment;
          });
    }

    private function normalizeTime(string $time): string
    {
        return Carbon::parse($time)->format('H:i:s');
    }


    public function cancel(Appointment $appointment): Appointment
{
    return DB::transaction(function () use ($appointment) {

        $appointment = Appointment::withoutGlobalScopes()
            ->lockForUpdate()
            ->findOrFail($appointment->id);

        if ($appointment->status !== AppointmentStatus::BOOKED) {
            throw ValidationException::withMessages([
                'appointment' => 'Only booked appointments can be cancelled.',
            ]);
        }

        $appointment->update([
            'status' => AppointmentStatus::CANCELLED,
        ]);

        AppointmentCancelled::dispatch($appointment);

        return $appointment;
    });
}
}
