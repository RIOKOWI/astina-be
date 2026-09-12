<?php

namespace App\Http\Requests\Due;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('rt') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'amount' => ['sometimes', 'integer', 'min:100'],
            'frequency' => ['sometimes', 'in:monthly,quarterly,yearly,one_time'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
