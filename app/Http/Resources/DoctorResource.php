<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->user?->name,
            'specialty' => $this->specialty?->value,
            'bio' => $this->bio,

            'schedules' => $this->whenLoaded(
                'schedules',
                fn () => $this->schedules->map(fn ($schedule) => [
                    'day_of_week' => $schedule->day_of_week?->value,
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                ])->values()
            ),
        ];
    }
}
