<?php

namespace App\Http\Resources\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'due_bill_id' => $this->due_bill_id,
            'due_bill' => $this->whenLoaded('dueBill', fn () => [
                'id' => $this->dueBill->id,
                'due' => [
                    'id' => $this->dueBill->due->id,
                    'name' => $this->dueBill->due->name,
                    'amount' => (int) $this->dueBill->due->amount,
                ],
                'amount' => (int) $this->dueBill->amount,
                'due_date' => $this->dueBill->due_date?->format('Y-m-d'),
                'status' => $this->dueBill->status,
            ]),
            'resident_id' => $this->resident_id,
            'resident' => $this->whenLoaded('resident', fn () => [
                'id' => $this->resident->id,
                'full_name' => $this->resident->full_name,
            ]),
            'amount' => (int) $this->amount,
            'method' => $this->method,
            'status' => $this->status,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'approved_by' => $this->approved_by,
            'approver' => $this->whenLoaded('approver', fn () => [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
            ]),
            'rejection_reason' => $this->when($this->status === 'rejected', $this->rejection_reason),
            'proofs' => $this->whenLoaded('proofs', fn () => PaymentProofResource::collection($this->proofs)),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
