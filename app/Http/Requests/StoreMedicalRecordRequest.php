<?php

namespace App\Http\Requests;

use App\Models\MedicalRecord;
use Illuminate\Foundation\Http\FormRequest;

class StoreMedicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', MedicalRecord::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'appointment_id' => [
                'required',
                'integer',
                'exists:appointments,id',
            ],
            'diagnosis' => ['nullable', 'string'],
            'prescription' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}