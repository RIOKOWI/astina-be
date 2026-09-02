<?php

namespace App\Http\Requests\Letter;

use Illuminate\Foundation\Http\FormRequest;

class SignLetterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('rt') ?? false;
    }

    public function rules(): array
    {
        return [
            'signature_image' => ['required', 'image', 'max:2048'],
        ];
    }
}
