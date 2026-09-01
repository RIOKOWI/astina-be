<?php

namespace App\Http\Resources\Household;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HouseholdResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'no_kk' => $this->no_kk,
            'address' => $this->address,
            'rt' => $this->rt,
            'rw' => $this->rw,
            'postal_code' => $this->postal_code,
            'status' => $this->status,
            'head_resident' => $this->whenLoaded('headResident', fn () => $this->headResident ? [
                'id' => $this->headResident->id,
                'full_name' => $this->headResident->full_name,
                'phone' => $this->headResident->phone,
            ] : null),
            'member_count' => $this->when(
                $this->member_count !== null,
                $this->member_count
            ),
            'members' => $this->whenLoaded('residents', fn () => HouseholdMemberResource::collection($this->residents)),
        ];
    }
}
