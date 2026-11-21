<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
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
            'facility_id' => ['nullable', 'integer', 'exists:facilities,id'],
            'patient_insurance_id' => ['nullable', 'integer', 'exists:patient_insurances,id'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.category' => ['sometimes', Rule::in(['consultation', 'laboratory', 'imaging', 'pharmacy', 'procedure', 'inpatient', 'service', 'other'])],
            'items.*.code' => ['nullable', 'string', 'max:60'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.provider_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
