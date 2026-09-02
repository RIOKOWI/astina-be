<?php

namespace App\Http\Requests\Sos;

use Illuminate\Foundation\Http\FormRequest;

class StoreSosAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'min:-90', 'max:90'],
            'longitude' => ['required', 'numeric', 'min:-180', 'max:180'],
            'location_text' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.required' => 'Latitude wajib diisi.',
            'latitude.numeric' => 'Latitude harus berupa angka.',
            'latitude.min' => 'Latitude minimal -90.',
            'latitude.max' => 'Latitude maksimal 90.',
            'longitude.required' => 'Longitude wajib diisi.',
            'longitude.numeric' => 'Longitude harus berupa angka.',
            'longitude.min' => 'Longitude minimal -180.',
            'longitude.max' => 'Longitude maksimal 180.',
        ];
    }
}
