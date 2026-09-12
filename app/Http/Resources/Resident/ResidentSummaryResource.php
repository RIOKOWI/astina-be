<?php

namespace App\Http\Resources\Resident;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResidentSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'nik' => $this->nik,
            'phone' => $this->phone,
            'status' => $this->status,
        ];

        $user = $this->relationLoaded('user') ? $this->user : null;
        $data['has_account'] = $user !== null;

        if ($user) {
            $data['account'] = [
                'id' => $user->id,
                'is_active' => $user->is_active,
            ];
        } else {
            $data['account'] = null;
        }

        return $data;
    }
}
