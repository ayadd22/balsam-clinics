<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicalRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'appointment_id' => $this->appointment_id,
            'doctor_id' => $this->doctor_id,
            'diagnosis' => $this->diagnosis,
            'prescription' => $this->prescription,
            'notes' => $this->notes,

            'doctor' => $this->whenLoaded(
                'doctor',
                fn () => [
                    'id' => $this->doctor->id,
                    'name' => $this->doctor->user?->name,
                ]
            ),

            'appointment' => $this->whenLoaded(
                'appointment',
                fn () => [
                    'id' => $this->appointment->id,
                    'reference_number' => $this->appointment->reference_number,
                    'appointment_date' => $this->appointment->appointment_date?->format('Y-m-d'),
                ]
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}