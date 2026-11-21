<?php

namespace App\Http\Requests\Calendar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                'scheduled',
                'confirmed',
                'checked_in',
                'in_room',
                'completed',
                'cancelled',
                'no_show',
            ])],
            'cancellation_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
