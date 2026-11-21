<?php

namespace App\Http\Requests\Pharmacy;

use Illuminate\Foundation\Http\FormRequest;

class StorePrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'encounter_id' => ['nullable', 'integer', 'exists:encounters,id'],
            'provider_id' => ['nullable', 'integer', 'exists:users,id'],
            'drug_id' => ['nullable', 'integer', 'exists:drugs,id'],
            'drug_name' => ['required_without:drug_id', 'nullable', 'string', 'max:200'],
            'dosage' => ['required', 'string', 'max:100'],
            'route' => ['nullable', 'string', 'max:40'],
            'frequency' => ['required', 'string', 'max:80'],
            'duration_days' => ['nullable', 'integer', 'between:1,365'],
            'quantity_prescribed' => ['required', 'integer', 'min:1'],
            'refills_allowed' => ['sometimes', 'integer', 'between:0,12'],
            'is_prn' => ['sometimes', 'boolean'],
            'allow_substitution' => ['sometimes', 'boolean'],
            'inhouse' => ['sometimes', 'boolean'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'indication' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
        ];
    }
}
