<?php

namespace App\Http\Resources\Complaint;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplaintCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'comment' => $this->comment,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->resident?->full_name,
                'role' => $this->user?->roles->first()?->code,
            ],
        ];
    }
}
