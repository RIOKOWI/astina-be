<?php

namespace App\Http\Requests\Complaint;

use Illuminate\Foundation\Http\FormRequest;

class StoreComplaintAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('rt') ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png',
                'max:10240',
            ],
        ];
    }
}
