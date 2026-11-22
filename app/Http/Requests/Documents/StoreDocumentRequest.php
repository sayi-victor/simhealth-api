<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $maxKb = (int) config('documents.max_kb', 25600);
        $extensions = implode(',', (array) config('documents.extensions', []));
        $categories = (array) config('documents.categories', ['general']);

        return [
            'file' => ['required', 'file', "max:{$maxKb}", "extensions:{$extensions}"],
            'category' => ['nullable', 'string', Rule::in($categories)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $maxMb = round(((int) config('documents.max_kb', 25600)) / 1024, 1);
        $extensions = implode(', ', (array) config('documents.extensions', []));

        return [
            'file.extensions' => "This file type is not allowed. Allowed types: {$extensions}.",
            'file.max' => "The document may not be larger than {$maxMb} MB.",
            'file.uploaded' => 'The document failed to upload. Check the file size and try again.',
        ];
    }
}
