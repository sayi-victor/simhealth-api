<?php

namespace App\Http\Requests\Calendar;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'provider_id' => ['nullable', 'integer', 'exists:users,id'],
            'facility_id' => ['nullable', 'integer', 'exists:facilities,id'],
            'category_id' => ['nullable', 'integer', 'exists:appointment_categories,id'],
            'title' => ['nullable', 'string', 'max:150'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'between:5,480'],
            'all_day' => ['sometimes', 'boolean'],
            'room' => ['nullable', 'string', 'max:40'],
            'reason' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'send_sms_reminder' => ['sometimes', 'boolean'],
            'send_email_reminder' => ['sometimes', 'boolean'],
            'allow_overlap' => ['sometimes', 'boolean'],
        ];
    }
}
