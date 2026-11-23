<?php

namespace App\Http\Controllers\Api;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMedicalRecordRequest;
use App\Http\Requests\UpdateMedicalRecordRequest;
use App\Http\Resources\MedicalRecordResource;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MedicalRecordController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', MedicalRecord::class);

        $user = auth()->user();

        $query = MedicalRecord::query()
            ->with(['doctor.user', 'appointment']);

        if ($user->role === UserRole::DOCTOR) {
            $doctorId = $user->doctor?->id;

            $query->where('doctor_id', $doctorId ?? 0);
        } elseif ($user->role === UserRole::PATIENT) {
            $patientId = $user->patient?->id;

            $query->whereHas('appointment', function ($query) use ($patientId) {
                $query->where('patient_id', $patientId ?? 0);
            });
        }

        return MedicalRecordResource::collection(
            $query->latest()->paginate(15)
        );
    }

    public function show(MedicalRecord $medicalRecord)
    {
        $this->authorize('view', $medicalRecord);

        $medicalRecord->load([
            'doctor.user',
            'appointment',
        ]);

        return new MedicalRecordResource($medicalRecord);
    }

    public function store(StoreMedicalRecordRequest $request)
    {
        $data = $request->validated();

        $medicalRecord = DB::transaction(function () use ($data) {
            $appointment = Appointment::query()
                ->lockForUpdate()
                ->findOrFail($data['appointment_id']);

            if ($appointment->status !== AppointmentStatus::COMPLETED) {
                throw ValidationException::withMessages([
                    'appointment_id' => 'Medical records can only be created for completed appointments.',
                ]);
            }

            if (MedicalRecord::where(
                'appointment_id',
                $appointment->id
            )->exists()) {
                throw ValidationException::withMessages([
                    'appointment_id' => 'A medical record already exists for this appointment.',
                ]);
            }

            $user = auth()->user();

            if (
                $user->role === UserRole::DOCTOR &&
                $user->doctor?->id !== $appointment->doctor_id
            ) {
                throw ValidationException::withMessages([
                    'appointment_id' => 'You can only create records for your own appointments.',
                ]);
            }

            return MedicalRecord::create([
                'appointment_id' => $appointment->id,
                'doctor_id' => $appointment->doctor_id,
                'diagnosis' => $data['diagnosis'] ?? null,
                'prescription' => $data['prescription'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        });

        $medicalRecord->load(['doctor.user', 'appointment']);

        return (new MedicalRecordResource($medicalRecord))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateMedicalRecordRequest $request,
        MedicalRecord $medicalRecord
    ) {
        $medicalRecord->update($request->validated());

        $medicalRecord->load(['doctor.user', 'appointment']);

        return new MedicalRecordResource($medicalRecord);
    }
}