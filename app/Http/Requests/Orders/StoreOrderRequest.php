<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
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
            'order_type' => ['required', Rule::in(['laboratory', 'imaging', 'procedure', 'service'])],
            'priority' => ['sometimes', Rule::in(['routine', 'urgent', 'stat'])],
            'specimen_type' => ['nullable', 'string', 'max:60'],
            'diagnosis' => ['nullable', 'string', 'max:255'],
            'clinical_history' => ['nullable', 'string'],
            'patient_instructions' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.catalog_item_id' => ['nullable', 'integer', 'exists:order_catalog_items,id'],
            'items.*.item_code' => ['required_without:items.*.catalog_item_id', 'nullable', 'string', 'max:60'],
            'items.*.item_name' => ['required_without:items.*.catalog_item_id', 'nullable', 'string', 'max:200'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }
}
