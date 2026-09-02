<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('warga') ?? false;
    }

    public function rules(): array
    {
        return [
            'due_bill_id' => ['required', 'integer'],
            'amount' => ['required', 'integer', 'min:100'],
            'method' => ['nullable', 'in:transfer,cash,qris'],
        ];
    }
}
