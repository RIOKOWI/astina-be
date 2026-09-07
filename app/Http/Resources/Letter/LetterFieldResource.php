<?php

namespace App\Http\Resources\Letter;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LetterFieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'field_key' => $this->field_key,
            'label' => $this->label,
            'field_type' => $this->field_type,
            'is_required' => $this->is_required,
            'sort_order' => $this->sort_order,
        ];
    }
}
