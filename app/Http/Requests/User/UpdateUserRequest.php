<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasRole('rt');
    }

    public function rules(): array
    {
        return [
            'phone' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('users')->ignore($this->route('user')),
            ],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('users')->ignore($this->route('user')),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->replace($this->only(['phone', 'email', 'is_active']));
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $targetUser = $this->route('user');
            $currentUser = auth()->user();

            if ($targetUser->id === $currentUser->id && $this->boolean('is_active') === false) {
                $validator->errors()->add('is_active', 'Anda tidak dapat menonaktifkan akun yang sedang digunakan.');
            }

            $disallowed = array_diff(array_keys($this->all()), ['phone', 'email', 'is_active']);
            foreach ($disallowed as $field) {
                $validator->errors()->add($field, 'Field ini tidak dapat diubah.');
            }
        });
    }
}
