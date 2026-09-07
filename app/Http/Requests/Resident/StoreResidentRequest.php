<?php

namespace App\Http\Requests\Resident;

use Illuminate\Foundation\Http\FormRequest;

class StoreResidentRequest extends FormRequest
{
    private ?array $originalInput = null;

    public function authorize(): bool
    {
        return auth()->user()->hasRole('rt');
    }

    public function prepareForValidation(): void
    {
        $this->originalInput = $this->request->all();
    }

    public function rules(): array
    {
        return [
            'nik' => ['required', 'string', 'max:20', 'unique:residents,nik'],
            'full_name' => ['required', 'string', 'max:255'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['required', 'in:male,female'],
            'religion' => ['nullable', 'string', 'max:50'],
            'marital_status' => ['required', 'in:single,married,divorced,widowed'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'last_education' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'joined_at' => ['nullable', 'date'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $allowed = ['nik', 'full_name', 'birth_place', 'birth_date', 'gender', 'religion', 'marital_status', 'occupation', 'last_education', 'phone', 'email', 'joined_at'];
                $unknown = array_diff(array_keys($this->originalInput ?? []), $allowed);
                if (! empty($unknown)) {
                    $field = array_keys($unknown)[0];
                    $validator->errors()->add($field, "Field {$field} tidak diizinkan.");
                }
            },
        ];
    }
}
