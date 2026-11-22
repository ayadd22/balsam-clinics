<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppointmentRequest;
use App\Models\Patient;
use App\Services\AppointmentService;
use App\Http\Resources\AppointmentResource;

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
}
