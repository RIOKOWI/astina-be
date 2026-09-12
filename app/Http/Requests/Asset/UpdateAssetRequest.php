<?php

namespace App\Http\Requests\Asset;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('rt') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'unit' => ['sometimes', 'required', 'string', 'max:50'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'purchase_date' => ['nullable', 'date'],
            'condition' => ['nullable', 'string', 'in:new,good,fair,poor'],
            'status' => ['nullable', 'string', 'in:available,in_use,maintenance,retired'],
            'quantity' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.prohibited' => 'Quantity tidak dapat diubah langsung. Gunakan fitur pergerakan asset.',
        ];
    }
}
