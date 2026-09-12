<?php

namespace App\Http\Requests\Household;

use Illuminate\Foundation\Http\FormRequest;

class StoreHouseholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasRole('rt');
    }

    public function rules(): array
    {
        return [
            'no_kk' => ['required', 'string', 'max:20', 'unique:households,no_kk'],
            'head_resident_id' => ['required', 'integer', 'exists:residents,id'],
            'address' => ['required', 'string', 'max:500'],
            'rt' => ['required', 'string', 'max:5'],
            'rw' => ['required', 'string', 'max:5'],
            'postal_code' => ['nullable', 'string', 'max:10'],
        ];
    }
}
