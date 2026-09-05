<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMyAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = auth()->user();

        return [
            'phone' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('users')->ignore($user),
            ],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user),
            ],
            'current_password' => ['sometimes', 'required', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = auth()->user();
            $phoneChanged = $this->filled('phone') && $this->phone !== $user->phone;
            $emailChanged = $this->filled('email') && $this->email !== $user->email;

            if (($phoneChanged || $emailChanged) && ! $this->filled('current_password')) {
                $validator->errors()->add('current_password', 'Password saat ini wajib diisi untuk mengubah phone atau email.');
            }

            if ($this->filled('current_password') && ! password_verify($this->current_password, $user->password)) {
                $validator->errors()->add('current_password', 'Password saat ini salah.');
            }

            $disallowed = array_diff(array_keys($this->all()), ['phone', 'email', 'current_password']);
            foreach ($disallowed as $field) {
                $validator->errors()->add($field, 'Field ini tidak dapat diubah.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'Nomor HP sudah digunakan.',
            'email.unique' => 'Email sudah digunakan.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->replace($this->only(['phone', 'email', 'current_password']));
    }
}
