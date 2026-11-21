<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'clinical_order_item_id' => ['nullable', 'integer', 'exists:clinical_order_items,id'],
            'result_code' => ['nullable', 'string', 'max:60'],
            'result_label' => ['required_without:clinical_order_item_id', 'nullable', 'string', 'max:200'],
            'value' => ['nullable', 'string', 'max:255'],
            'units' => ['nullable', 'string', 'max:40'],
            'reference_range' => ['nullable', 'string', 'max:120'],
            'abnormal_flag' => ['sometimes', Rule::in(['normal', 'low', 'high', 'critical', 'abnormal'])],
            'findings' => ['nullable', 'string'],
            'impression' => ['nullable', 'string'],
            'comments' => ['nullable', 'string'],
            'result_status' => ['sometimes', Rule::in(['preliminary', 'final', 'corrected'])],
        ];
    }
}
