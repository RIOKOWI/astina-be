<?php

namespace App\Http\Resources\Resident;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResidentAdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = (new ResidentResource($this))->toArray($request);

        $data['household'] = null;
        $currentHousehold = null;
        if ($this->relationLoaded('households') && $this->households->isNotEmpty()) {
            $currentHousehold = $this->households->first();
            $data['household'] = [
                'id' => $currentHousehold->id,
                'no_kk' => $currentHousehold->no_kk,
                'address' => $currentHousehold->address,
                'rt' => $currentHousehold->rt,
                'rw' => $currentHousehold->rw,
                'postal_code' => $currentHousehold->postal_code,
                'status' => $currentHousehold->status,
            ];
        } elseif ($this->relationLoaded('households') === false && $this->headedHousehold) {
            $currentHousehold = $this->headedHousehold;
            $data['household'] = [
                'id' => $currentHousehold->id,
                'no_kk' => $currentHousehold->no_kk,
                'address' => $currentHousehold->address,
                'rt' => $currentHousehold->rt,
                'rw' => $currentHousehold->rw,
                'postal_code' => $currentHousehold->postal_code,
                'status' => $currentHousehold->status,
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

        $data['ktp'] = null;
        if ($this->relationLoaded('media')) {
            $ktp = $this->media->firstWhere('collection', Media::COLLECTION_KTP);
            if ($ktp) {
                $data['ktp'] = [
                    'exists' => true,
                    'mime_type' => $ktp->mime_type,
                    'file_size' => $ktp->file_size,
                    'file_name' => $ktp->file_name,
                    'url' => "/api/v1/residents/{$this->id}/documents/ktp/file",
                ];
            } else {
                $data['ktp'] = ['exists' => false];
            }
        }

        $data['kk'] = null;
        if ($currentHousehold && $currentHousehold->relationLoaded('media')) {
            $kk = $currentHousehold->media->firstWhere('collection', Media::COLLECTION_KK);
            if ($kk) {
                $data['kk'] = [
                    'exists' => true,
                    'no_kk' => $currentHousehold->no_kk,
                    'mime_type' => $kk->mime_type,
                    'file_size' => $kk->file_size,
                    'file_name' => $kk->file_name,
                    'url' => "/api/v1/residents/{$this->id}/documents/kk/file",
                ];
            } else {
                $data['kk'] = ['exists' => false];
            }
        }

        return $data;
    }
}
