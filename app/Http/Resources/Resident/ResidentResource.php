<?php

namespace App\Http\Resources\Resident;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResidentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nik' => $this->nik,
            'full_name' => $this->full_name,
            'birth_place' => $this->birth_place,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'gender' => $this->gender,
            'religion' => $this->religion,
            'occupation' => $this->occupation,
            'last_education' => $this->last_education,
            'marital_status' => $this->marital_status,
            'phone' => $this->phone,
            'email' => $this->email,
            'status' => $this->status,
        ];
    }
}
