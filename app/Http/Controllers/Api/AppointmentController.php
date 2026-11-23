<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppointmentRequest;
use App\Models\Patient;
use App\Services\AppointmentService;
use App\Http\Resources\AppointmentResource;
use App\Http\Requests\CheckAppointmentAvailabilityRequest;
use App\Services\AppointmentAvailabilityService;

class AppointmentController extends Controller
{
    public function store(
        StoreAppointmentRequest $request,
        AppointmentService $appointmentService
    ) {
        $user = $request->user();
        $data = $request->validated();

        // Patients can only book for themselves.
        if ($user->role === UserRole::PATIENT) {
            $patient = $user->patient;

            if (!$patient) {
                return ApiResponse::error(
                    'Patient profile not found.',
                    404
                );
            }
        } else {
            // Admin and receptionist can book for another patient.
            $patient = Patient::findOrFail($data['patient_id']);
        }

        $appointment = $appointmentService->create(
            $patient,
            $data
        );

       return ApiResponse::success(
         new AppointmentResource(
        $appointment->load(['patient', 'doctor.user', 'branch'])
        ),
        'Appointment created successfully.',
        201
       );
    }

   public function cancel(
    \Illuminate\Http\Request $request,
    \App\Models\Appointment $appointment,
    AppointmentService $appointmentService
    ) {
    if (!$request->user()->can('cancel', $appointment)) {
        return ApiResponse::error(
            'You are not authorized to cancel this appointment.',
            403
        );
    }

    $appointment = $appointmentService->cancel($appointment);

    return ApiResponse::success(
        new AppointmentResource(
            $appointment->load(['patient', 'doctor.user', 'branch'])
        ),
        'Appointment cancelled successfully.'
    );
  }

public function availability(
    CheckAppointmentAvailabilityRequest $request,
    AppointmentAvailabilityService $availabilityService
) {
    $data = $request->validated();

    $available = $availabilityService->isAvailable(
        (int) $data['doctor_id'],
        (int) $data['branch_id'],
        $data['appointment_date'],
        $data['start_time'],
        $data['end_time']
    );

    return ApiResponse::success(
        ['available' => $available],
        $available
            ? 'The requested appointment time is available.'
            : 'The requested appointment time is not available.'
    );
}


}
