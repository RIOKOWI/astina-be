<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateResidentAccountRequest extends FormRequest
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
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Nomor telepon wajib diisi.',
            'phone.unique' => 'Nomor telepon sudah digunakan oleh akun lain.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan oleh akun lain.',
            'password.required' => 'Password wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ];
    }

    public function after(): array
    {
        $allowed = ['phone', 'email', 'password', 'password_confirmation'];

        return [
            function ($validator) use ($allowed): void {
                $unknown = array_diff(array_keys($this->originalInput ?? []), $allowed);
                if (! empty($unknown)) {
                    $field = array_keys($unknown)[0];
                    $validator->errors()->add($field, "Field {$field} tidak diizinkan.");
                }
            },
        ];
    }
}
