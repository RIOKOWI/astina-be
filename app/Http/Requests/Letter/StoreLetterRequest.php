<?php

namespace App\Http\Requests\Letter;

use Illuminate\Foundation\Http\FormRequest;

class StoreLetterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('warga') ?? false;
    }

    public function rules(): array
    {
        return [
            'letter_type_id' => ['required', 'integer', 'exists:letter_types,id'],
            'purpose' => ['nullable', 'string', 'max:1000'],
            'fields' => ['required', 'array'],
            'fields.*' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
