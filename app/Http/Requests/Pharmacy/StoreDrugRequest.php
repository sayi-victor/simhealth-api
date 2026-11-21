<?php

namespace App\Http\Requests\Pharmacy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDrugRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:60', Rule::unique('drugs', 'code')],
            'name' => ['required', 'string', 'max:200'],
            'generic_name' => ['nullable', 'string', 'max:200'],
            'rxnorm_code' => ['nullable', 'string', 'max:40'],
            'form' => ['sometimes', Rule::in(['tablet', 'capsule', 'syrup', 'injection', 'cream', 'ointment', 'drops', 'inhaler', 'suppository', 'powder', 'other'])],
            'strength' => ['nullable', 'string', 'max:60'],
            'route' => ['sometimes', Rule::in(['oral', 'iv', 'im', 'sc', 'topical', 'inhalation', 'ophthalmic', 'otic', 'rectal', 'sublingual'])],
            'unit' => ['sometimes', 'string', 'max:30'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'reorder_level' => ['sometimes', 'integer', 'min:0'],
            'requires_prescription' => ['sometimes', 'boolean'],
            'is_controlled' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
