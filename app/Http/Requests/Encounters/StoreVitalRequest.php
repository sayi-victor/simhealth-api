<?php

namespace App\Http\Requests\Encounters;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVitalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'recorded_at' => ['nullable', 'date'],
            'bp_systolic' => ['nullable', 'integer', 'between:30,350'],
            'bp_diastolic' => ['nullable', 'integer', 'between:20,250'],
            'pulse' => ['nullable', 'integer', 'between:20,300'],
            'respiration' => ['nullable', 'integer', 'between:4,100'],
            'temperature' => ['nullable', 'numeric', 'between:30,45'],
            'temp_method' => ['nullable', Rule::in(['oral', 'tympanic', 'axillary', 'rectal', 'temporal'])],
            'weight_kg' => ['nullable', 'numeric', 'between:0.5,500'],
            'height_cm' => ['nullable', 'numeric', 'between:20,280'],
            'oxygen_saturation' => ['nullable', 'numeric', 'between:0,100'],
            'oxygen_sat_condition' => ['nullable', 'string', 'max:50'],
            'waist_cm' => ['nullable', 'numeric', 'between:10,300'],
            'head_cm' => ['nullable', 'numeric', 'between:10,100'],
            'pain_score' => ['nullable', 'integer', 'between:0,10'],
            'triage_level' => ['nullable', Rule::in(['resuscitation', 'emergent', 'urgent', 'less_urgent', 'non_urgent'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
