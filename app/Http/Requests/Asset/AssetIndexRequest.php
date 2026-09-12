<?php

namespace App\Http\Requests\Asset;

use Illuminate\Foundation\Http\FormRequest;

class AssetIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('rt') ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'condition' => ['nullable', 'string', 'in:new,good,fair,poor'],
            'status' => ['nullable', 'string', 'in:available,in_use,maintenance,retired'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
