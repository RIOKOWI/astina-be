<?php

namespace App\Http\Resources\Resident;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResidentDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'resident' => $this->resource['resident'],
            'ktp' => $this->resource['ktp'],
            'household' => $this->resource['household'],
            'kk' => $this->resource['kk'],
        ];
    }
}
