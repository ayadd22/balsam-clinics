<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;


class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Appointment::class) ?? false;
    }

    public function rules(): array
    {
        $rules = [
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

        if (in_array($this->user()->role, [
            UserRole::ADMIN,
            UserRole::RECEPTIONIST,
        ])) {
            $rules['patient_id'] = [
                'required',
                'integer',
                'exists:patients,id',
            ];
        } else {
            $rules['patient_id'] = ['prohibited'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
    $validator->after(function (Validator $validator) {
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
