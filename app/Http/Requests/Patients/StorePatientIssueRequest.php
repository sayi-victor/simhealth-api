<?php

namespace App\Http\Requests\Patients;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatientIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['medical_problem', 'allergy', 'medication', 'surgery', 'dental'])],
            'title' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:60'],
            'severity' => ['nullable', Rule::in(['mild', 'moderate', 'severe', 'life_threatening'])],
            'reaction' => ['nullable', 'string', 'max:255'],
            'onset_date' => ['nullable', 'date'],
            'resolved_date' => ['nullable', 'date', 'after_or_equal:onset_date'],
            'is_active' => ['sometimes', 'boolean'],
            'comments' => ['nullable', 'string'],
        ];
    }
}
