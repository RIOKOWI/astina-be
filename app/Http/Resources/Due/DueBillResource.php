<?php

namespace App\Http\Resources\Due;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DueBillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'due_id' => $this->due_id,
            'due' => new DueResource($this->whenLoaded('due')),
            'resident_id' => $this->resident_id,
            'amount' => (int) $this->amount,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
