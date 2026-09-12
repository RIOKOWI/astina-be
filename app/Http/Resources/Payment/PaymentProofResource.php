<?php

namespace App\Http\Resources\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentProofResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_id' => $this->payment_id,
            'url' => $this->path ? asset("storage/{$this->path}") : null,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'file_size' => (int) $this->file_size,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
