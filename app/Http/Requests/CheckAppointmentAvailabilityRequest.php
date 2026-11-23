<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CheckAppointmentAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Appointment::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'doctor_id' => [
                'required',
                'integer',
                'exists:doctors,id',
            ],
            'branch_id' => [
                'required',
                'integer',
                'exists:branches,id',
            ],
            'appointment_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:today',
            ],
            'start_time' => [
                'required',
                'regex:/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/',
            ],
            'end_time' => [
                'required',
                'regex:/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $start = $this->input('start_time');
            $end = $this->input('end_time');

            if ($start && $end && $start >= $end) {
                $validator->errors()->add(
                    'end_time',
                    'End time must be after start time.'
                );
            }

            $user = $this->user();

            if (
                $user?->role === UserRole::RECEPTIONIST &&
                $this->filled('branch_id')
            ) {
                $hasBranchAccess = $user->branches()
                    ->where('branches.id', $this->input('branch_id'))
                    ->exists();

                if (!$hasBranchAccess) {
                    $validator->errors()->add(
                        'branch_id',
                        'You are not assigned to this branch.'
                    );
                }
            }
        });
    }
}
