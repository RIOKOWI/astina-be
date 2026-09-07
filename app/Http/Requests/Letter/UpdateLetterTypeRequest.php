<?php

namespace App\Http\Requests\Letter;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLetterTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('rt') ?? false;
    }

    public function rules(): array
    {
        $letterTypeId = $this->route('letter_type')->id ?? $this->route('letter_type');

        return [
            'code' => ['sometimes', 'string', 'max:20', Rule::unique('letter_types', 'code')->ignore($letterTypeId)],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'template_path' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'fields' => ['nullable', 'array'],
            'fields.*.field_key' => ['required_with:fields', 'string', 'max:50', 'regex:/^[a-z_]+$/'],
            'fields.*.label' => ['required_with:fields', 'string', 'max:255'],
            'fields.*.field_type' => ['required_with:fields', 'string', 'in:text,number,date,textarea,select,checkbox'],
            'fields.*.is_required' => ['nullable', 'boolean'],
            'fields.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'fields.*.field_key.regex' => 'field_key hanya boleh huruf kecil dan underscore.',
        ];
    }
}
