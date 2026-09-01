<?php

namespace App\Http\Resources\Complaint;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplaintResource extends JsonResource
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
            'rejection_reason' => $this->rejection_reason,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'resident' => $this->whenLoaded('resident', fn () => [
                'id' => $this->resident?->id,
                'full_name' => $this->resident?->full_name,
            ]),
            'assigned_to' => $this->whenLoaded('assignee', fn () => [
                'id' => $this->assignee?->id,
                'name' => $this->assignee?->resident?->full_name,
            ]),
            'attachments' => ComplaintAttachmentResource::collection($this->whenLoaded('attachments')),
            'comments' => ComplaintCommentResource::collection($this->whenLoaded('comments')),
            'attachment_count' => $this->attachments_count ?? $this->attachments->count(),
        ];
    }
}
