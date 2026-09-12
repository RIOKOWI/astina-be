<?php

namespace App\Http\Resources\Sos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SosAlertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $triggerer = $this->triggerer;
        $resident = $triggerer?->resident;

        $currentHousehold = null;
        if ($resident) {
            $households = $resident->households;
            $current = $households->firstWhere('pivot.is_current', true);
            if ($current) {
                $currentHousehold = [
                    'id' => $current->id,
                    'no_kk' => $current->no_kk,
                    'address' => $current->address,
                    'rt' => $current->rt,
                    'rw' => $current->rw,
                    'postal_code' => $current->postal_code,
                ];
            }
        }

        return [
            'id' => $this->id,
            'status' => $this->status,
            'triggered_at' => $this->triggered_at?->toIso8601String(),
            'triggered_by' => $triggerer ? [
                'id' => $triggerer->id,
                'name' => $triggerer->resident?->full_name ?? $triggerer->phone,
            ] : null,
            'household' => $currentHousehold,
            'location' => [
                'latitude' => (float) $this->latitude,
                'longitude' => (float) $this->longitude,
                'text' => $this->location_text,
            ],
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'resolved_by' => $this->whenLoaded('resolver', fn () => $this->resolver ? [
                'id' => $this->resolver->id,
                'name' => $this->resolver->resident?->full_name ?? $this->resolver->phone,
            ] : null),
            'responses' => SosResponseResource::collection($this->whenLoaded('responses')),
        ];
    }
}
