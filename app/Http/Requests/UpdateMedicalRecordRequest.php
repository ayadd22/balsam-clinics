<?php

namespace App\Http\Requests;

use App\Models\MedicalRecord;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMedicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'update',
            $this->route('medicalRecord')
        ) ?? false;
    }

    public function rules(): array
    {
        return [
            'appointment_id' => ['prohibited'],
            'doctor_id' => ['prohibited'],
            'diagnosis' => ['sometimes', 'nullable', 'string'],
            'prescription' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}