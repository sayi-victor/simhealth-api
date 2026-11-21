<?php

namespace App\Http\Requests\Calendar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
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
            'provider_id' => ['nullable', 'integer', 'exists:users,id'],
            'facility_id' => ['nullable', 'integer', 'exists:facilities,id'],
            'category_id' => ['nullable', 'integer', 'exists:appointment_categories,id'],
            'title' => ['nullable', 'string', 'max:150'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'duration_minutes' => ['nullable', 'integer', 'between:5,480'],
            'all_day' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['scheduled', 'confirmed'])],
            'room' => ['nullable', 'string', 'max:40'],
            'reason' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'send_sms_reminder' => ['sometimes', 'boolean'],
            'send_email_reminder' => ['sometimes', 'boolean'],
            'allow_overlap' => ['sometimes', 'boolean'],
        ];
    }
}
