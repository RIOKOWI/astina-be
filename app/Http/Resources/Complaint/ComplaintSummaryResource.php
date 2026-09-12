<?php

namespace App\Http\Resources\Complaint;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplaintSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_no' => $this->reference_no,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'status' => $this->status,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'resident' => $this->whenLoaded('resident', fn () => [
                'id' => $this->resident?->id,
                'full_name' => $this->resident?->full_name,
            ]),
            'attachment_count' => $this->attachments_count ?? $this->attachments->count(),
        ];
    }
}
