<?php

namespace App\Http\Requests\Encounters;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitEncounterFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'form_definition_id' => ['required', 'integer', 'exists:form_definitions,id'],
            'status' => ['sometimes', Rule::in(['draft', 'completed'])],
            'data' => ['required', 'array'],
        ];
    }
}
