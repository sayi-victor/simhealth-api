<?php

namespace App\Http\Requests\Encounters;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEncounterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'facility_id' => ['nullable', 'integer', 'exists:facilities,id'],
            'provider_id' => ['nullable', 'integer', 'exists:users,id'],
            'supervisor_id' => ['nullable', 'integer', 'exists:users,id'],
            'encounter_date' => ['sometimes', 'date'],
            'onset_date' => ['nullable', 'date'],
            'visit_category' => ['sometimes', Rule::in(['outpatient', 'inpatient', 'emergency', 'follow_up', 'antenatal', 'surgery', 'telehealth'])],
            'sensitivity' => ['sometimes', Rule::in(['normal', 'high'])],
            'status' => ['sometimes', Rule::in(['open', 'in_progress', 'closed', 'cancelled'])],
            'shift' => ['nullable', Rule::in(['day', 'night'])],
            'room_number' => ['nullable', 'string', 'max:40'],
            'reason' => ['sometimes', 'required', 'string'],
            'referral_source' => ['nullable', 'string', 'max:80'],
            'nhis_vcode' => ['nullable', 'string', 'max:20'],
            'billing_note' => ['nullable', 'string'],
        ];
    }
}
