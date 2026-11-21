<?php

namespace App\Http\Requests\Pharmacy;

use Illuminate\Foundation\Http\FormRequest;

class StoreDrugLotRequest extends FormRequest
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
            'lot_number' => ['required', 'string', 'max:60'],
            'manufacturer' => ['nullable', 'string', 'max:150'],
            'quantity_on_hand' => ['required', 'integer', 'min:1'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'expires_at' => ['nullable', 'date'],
            'received_at' => ['nullable', 'date'],
            'invoice_number' => ['nullable', 'string', 'max:60'],
        ];
    }
}
