<?php

namespace App\Http\Requests\Household;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHouseholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasRole('rt');
    }

    public function rules(): array
    {
        return [
            'no_kk' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('households')->ignore($this->route('household')),
            ],
            'address' => ['sometimes', 'required', 'string', 'max:500'],
            'rt' => ['sometimes', 'required', 'string', 'max:5'],
            'rw' => ['sometimes', 'required', 'string', 'max:5'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->replace($this->only(['no_kk', 'address', 'rt', 'rw', 'postal_code', 'status']));
    }
}
