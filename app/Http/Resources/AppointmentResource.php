<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,

            'appointment_date' => $this->appointment_date?->format('Y-m-d'),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,

            'status' => $this->status?->value,

            'patient' => $this->whenLoaded(
                'patient',
                fn () => [
                    'id' => $this->patient->id,
                    'full_name' => $this->patient->full_name,
                    'phone' => $this->patient->phone,
                ]
            ),

            'doctor' => $this->whenLoaded(
                'doctor',
                fn () => [
                    'id' => $this->doctor->id,
                    'name' => $this->doctor->user?->name,
                    'specialty' => $this->doctor->specialty?->value,
                ]
            ),

            'branch' => $this->whenLoaded(
                'branch',
                fn () => [
                    'id' => $this->branch->id,
                    'name' => $this->branch->name,
                    'city' => $this->branch->city,
                ]
            ),

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
