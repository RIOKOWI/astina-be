<?php

namespace App\Http\Requests\Resident;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateResidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasRole('rt');
    }

    public function rules(): array
    {
        return [
            'nik' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('residents')->ignore($this->route('resident')),
            ],
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'birth_place' => ['sometimes', 'nullable', 'string', 'max:100'],
            'birth_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'gender' => ['sometimes', Rule::in(['male', 'female'])],
            'religion' => ['sometimes', 'nullable', 'string', 'max:50'],
            'marital_status' => ['sometimes', Rule::in(['single', 'married', 'divorced', 'widowed'])],
            'occupation' => ['sometimes', 'nullable', 'string', 'max:100'],
            'last_education' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'moved'])],
            'joined_at' => ['sometimes', 'nullable', 'date'],
            'left_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:joined_at'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $resident = $this->route('resident');
            $disallowed = array_diff(array_keys($this->all()), [
                'nik', 'full_name', 'birth_place', 'birth_date', 'gender',
                'religion', 'marital_status', 'occupation', 'last_education',
                'status', 'joined_at', 'left_at', 'phone', 'email',
            ]);
            foreach ($disallowed as $field) {
                $validator->errors()->add($field, 'Field ini tidak dapat diubah.');
            }

            if ($this->has('phone') || $this->has('email')) {
                $linkedUser = $resident->user;
                if ($linkedUser) {
                    $validator->errors()->add('phone', 'Residen ini memiliki akun. Gunakan /users/{id} untuk mengubah kontak.');
                }
            }
        });
    }
}
