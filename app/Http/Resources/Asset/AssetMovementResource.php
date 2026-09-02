<?php

namespace App\Http\Resources\Asset;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_id' => $this->asset_id,
            'type' => $this->type,
            'quantity' => $this->quantity,
            'description' => $this->description,
            'movement_at' => $this->movement_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'created_by' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->resident?->full_name ?? $this->creator?->phone,
            ]),
        ];
    }
}
