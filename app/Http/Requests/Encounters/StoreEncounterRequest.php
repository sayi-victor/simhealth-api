<?php

namespace App\Http\Requests\Encounters;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEncounterRequest extends FormRequest
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
            'facility_id' => ['nullable', 'integer', 'exists:facilities,id'],
            'billing_facility_id' => ['nullable', 'integer', 'exists:facilities,id'],
            'provider_id' => ['nullable', 'integer', 'exists:users,id'],
            'supervisor_id' => ['nullable', 'integer', 'exists:users,id'],
            'insurance_id' => ['nullable', 'integer', 'exists:patient_insurances,id'],
            'encounter_date' => ['nullable', 'date'],
            'onset_date' => ['nullable', 'date'],
            'visit_category' => ['sometimes', Rule::in(['outpatient', 'inpatient', 'emergency', 'follow_up', 'antenatal', 'surgery', 'telehealth'])],
            'sensitivity' => ['sometimes', Rule::in(['normal', 'high'])],
            'shift' => ['nullable', Rule::in(['day', 'night'])],
            'room_number' => ['nullable', 'string', 'max:40'],
            'reason' => ['required', 'string'],
            'referral_source' => ['nullable', 'string', 'max:80'],
            'nhis_vcode' => ['nullable', 'string', 'max:20'],
            'price_level' => ['nullable', 'string', 'max:40'],
            'billing_note' => ['nullable', 'string'],
        ];
    }
}
