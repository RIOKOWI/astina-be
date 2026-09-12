<?php

namespace App\Http\Requests\Asset;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssetMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('rt') ?? false;
    }

    public function rules(): array
    {
        $type = $this->input('type');

        return [
            'type' => ['required', 'string', 'in:in,out,adjustment'],
            'quantity' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($type) {
                    if (in_array($type, ['in', 'out'], true) && $value <= 0) {
                        $fail('Quantity untuk pergerakan masuk dan keluar harus lebih dari 0.');
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'movement_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'Tipe pergerakan harus salah satu dari: in, out, adjustment.',
            'quantity.integer' => 'Quantity harus berupa bilangan bulat.',
        ];
    }
}
