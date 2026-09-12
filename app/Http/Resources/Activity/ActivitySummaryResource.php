<?php

namespace App\Http\Resources\Activity;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivitySummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $readStatusMap = $this->additional['read_status_map'] ?? [];

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'start_at' => $this->start_at?->toIso8601String(),
            'end_at' => $this->end_at?->toIso8601String(),
            'status' => $this->status,
            'attachment_count' => $this->attachments_count ?? $this->attachments->count(),
            'is_read' => isset($readStatusMap[$this->id]),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
