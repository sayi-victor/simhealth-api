<?php

namespace App\Http\Requests\Patients;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePatientHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lifestyle' => ['sometimes', 'nullable', 'array'],
            'family_history' => ['sometimes', 'nullable', 'array'],
            'surgical_history' => ['sometimes', 'nullable', 'array'],
            'preventive_exams' => ['sometimes', 'nullable', 'array'],
            'mental_health' => ['sometimes', 'nullable', 'array'],
            'additional_notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
