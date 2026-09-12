<?php

namespace App\Http\Requests\Resident;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMyResidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nik' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('residents')->ignore($this->user()->resident->id ?? 0),
            ],
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'birth_place' => ['sometimes', 'nullable', 'string', 'max:100'],
            'birth_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'gender' => ['sometimes', Rule::in(['male', 'female'])],
            'religion' => ['sometimes', 'nullable', 'string', 'max:50'],
            'marital_status' => ['sometimes', Rule::in(['single', 'married', 'divorced', 'widowed'])],
            'occupation' => ['sometimes', 'nullable', 'string', 'max:100'],
            'last_education' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'nik.unique' => 'NIK sudah terdaftar.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nik' => 'NIK',
            'full_name' => 'Nama lengkap',
            'birth_place' => 'Tempat lahir',
            'birth_date' => 'Tanggal lahir',
            'gender' => 'Jenis kelamin',
            'religion' => 'Agama',
            'marital_status' => 'Status perkawinan',
            'occupation' => 'Pekerjaan',
            'last_education' => 'Pendidikan terakhir',
        ];
    }

    protected function prepareForValidation(): void
    {
        $allowed = ['nik', 'full_name', 'birth_place', 'birth_date', 'gender', 'religion', 'marital_status', 'occupation', 'last_education'];
        $data = $this->only($allowed);

        foreach (array_keys($this->all()) as $key) {
            if (! in_array($key, $allowed)) {
                $data[$key] = $this->input($key);
            }
        }

        $this->replace($data);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $disallowed = array_diff(array_keys($this->all()), ['nik', 'full_name', 'birth_place', 'birth_date', 'gender', 'religion', 'marital_status', 'occupation', 'last_education']);
            foreach ($disallowed as $field) {
                $validator->errors()->add($field, 'Field ini tidak dapat diubah.');
            }
        });
    }
}
