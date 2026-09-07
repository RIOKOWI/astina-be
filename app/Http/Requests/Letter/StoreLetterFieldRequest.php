<?php

namespace App\Http\Requests\Letter;

use Illuminate\Foundation\Http\FormRequest;

class StoreLetterFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('rt') ?? false;
    }

    public function rules(): array
    {
        $letterTypeId = $this->route('letter_type')->id ?? $this->route('letter_type');

        return [
            'field_key' => ['required', 'string', 'max:50', 'regex:/^[a-z_]+$/', "unique:letter_fields,field_key,NULL,id,letter_type_id,$letterTypeId"],
            'label' => ['required', 'string', 'max:255'],
            'field_type' => ['required', 'string', 'in:text,number,date,textarea,select,checkbox'],
            'is_required' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'field_key.regex' => 'field_key hanya boleh huruf kecil dan underscore.',
            'field_key.unique' => 'field_key sudah digunakan pada jenis surat ini.',
        ];
    }
}
