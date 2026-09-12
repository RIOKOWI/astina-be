<?php

namespace App\Http\Resources\Sos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SosResponseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'response' => $this->response,
            'responded_at' => $this->responded_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->resident?->full_name ?? $this->user?->phone,
            ]),
        ];
    }
}
