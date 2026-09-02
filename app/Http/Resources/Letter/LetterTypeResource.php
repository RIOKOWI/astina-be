<?php

namespace App\Http\Resources\Letter;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LetterTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'fields' => $this->when($this->relationLoaded('fields'), function () {
                return $this->fields
                    ->sortBy('sort_order')
                    ->map(fn ($field) => [
                        'field_key' => $field->field_key,
                        'label' => $field->label,
                        'field_type' => $field->field_type,
                        'is_required' => $field->is_required,
                        'sort_order' => $field->sort_order,
                    ])
                    ->values()
                    ->toArray();
            }),
        ];
    }
}
