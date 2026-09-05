<?php

namespace App\Http\Resources\Resident;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResidentAdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = (new ResidentResource($this))->toArray($request);

        $data['household'] = null;
        if ($this->relationLoaded('households') && $this->households->isNotEmpty()) {
            $household = $this->households->first();
            $data['household'] = [
                'id' => $household->id,
                'no_kk' => $household->no_kk,
                'address' => $household->address,
                'rt' => $household->rt,
                'rw' => $household->rw,
                'postal_code' => $household->postal_code,
                'status' => $household->status,
            ];
        } elseif ($this->relationLoaded('households') === false && $this->headedHousehold) {
            $household = $this->headedHousehold;
            $data['household'] = [
                'id' => $household->id,
                'no_kk' => $household->no_kk,
                'address' => $household->address,
                'rt' => $household->rt,
                'rw' => $household->rw,
                'postal_code' => $household->postal_code,
                'status' => $household->status,
            ];
        }

        $data['account'] = null;
        if ($this->relationLoaded('user') && $this->user) {
            $data['account'] = [
                'id' => $this->user->id,
                'phone' => $this->user->phone,
                'email' => $this->user->email,
                'is_active' => $this->user->is_active,
                'roles' => $this->user->relationLoaded('roles')
                    ? $this->user->roles->map(fn ($role) => [
                        'code' => $role->code,
                        'name' => $role->name,
                    ])
                    : [],
            ];
        }

        return $data;
    }
}
