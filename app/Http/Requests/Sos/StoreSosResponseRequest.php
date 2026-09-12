<?php

namespace App\Http\Requests\Sos;

use Illuminate\Foundation\Http\FormRequest;

class StoreSosResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'response' => ['required', 'string', 'in:coming,not_available'],
        ];
    }

    public function messages(): array
    {
        return [
            'response.required' => 'Response wajib diisi.',
            'response.in' => 'Response harus berupa "coming" atau "not_available".',
        ];
    }
}
