<?php

namespace App\Http\Resources\Asset;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'category' => $this->category,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'purchase_price' => $this->purchase_price,
            'purchase_date' => $this->purchase_date?->toDateString(),
            'condition' => $this->condition,
            'status' => $this->status,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'recent_movements' => AssetMovementResource::collection(
                $this->whenLoaded('movements', fn () => $this->movements->sortByDesc('movement_at')->take(10)
                )
            ),
            'movements_count' => $this->when(
                isset($this->movements_count),
                $this->movements_count ?? $this->movements->count()
            ),
        ];
    }
}
