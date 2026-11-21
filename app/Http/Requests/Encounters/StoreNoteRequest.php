<?php

namespace App\Http\Requests\Encounters;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'note_type' => ['required', Rule::in(['consultation', 'soap', 'nursing', 'observation', 'discharge', 'referral'])],
            'title' => ['nullable', 'string', 'max:255'],
            'subjective' => ['nullable', 'string'],
            'objective' => ['nullable', 'string'],
            'assessment' => ['nullable', 'string'],
            'plan' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
        ];
    }
}
