<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'payer_type' => ['sometimes', Rule::in(['patient', 'insurance', 'adjustment'])],
            'payment_method' => ['required', Rule::in(['cash', 'mobile_money', 'card', 'bank_transfer', 'cheque', 'insurance_remittance', 'adjustment'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
