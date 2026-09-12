<?php

namespace App\Http\Requests\Household;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddHouseholdMemberRequest extends FormRequest
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
            'resident_id' => ['required', 'integer', 'exists:residents,id'],
            'relationship' => ['required', Rule::in(['spouse', 'child', 'parent', 'sibling', 'other'])],
            'joined_at' => ['nullable', 'date'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $allowed = ['resident_id', 'relationship', 'joined_at'];
                $unknown = array_diff(array_keys($this->originalInput ?? []), $allowed);
                if (! empty($unknown)) {
                    $field = array_keys($unknown)[0];
                    $validator->errors()->add($field, "Field {$field} tidak diizinkan.");
                }
            },
        ];
    }
}
