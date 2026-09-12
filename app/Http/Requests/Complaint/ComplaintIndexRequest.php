<?php

namespace App\Http\Requests\Complaint;

use Illuminate\Foundation\Http\FormRequest;

class ComplaintIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:submitted,reviewed,in_progress,resolved,closed,rejected'],
            'category' => ['nullable', 'string', 'in:facility,security,cleanliness,noise,dispute,other'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
