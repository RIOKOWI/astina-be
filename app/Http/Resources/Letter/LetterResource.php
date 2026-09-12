<?php

namespace App\Http\Resources\Letter;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LetterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_no' => $this->reference_no,
            'letter_type' => [
                'id' => $this->letterType->id,
                'code' => $this->letterType->code,
                'name' => $this->letterType->name,
            ],
            'resident' => [
                'id' => $this->resident->id,
                'full_name' => $this->resident->full_name,
                'nik' => $this->resident->nik,
            ],
            'purpose' => $this->purpose,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'field_values' => $this->when($this->relationLoaded('fieldValues'), function () {
                return $this->fieldValues->map(fn ($fv) => [
                    'field_key' => $fv->letterField->field_key,
                    'label' => $fv->letterField->label,
                    'value' => $fv->value,
                ])->toArray();
            }),
            'approvals' => $this->when($this->relationLoaded('approvals'), function () {
                return $this->approvals->map(fn ($a) => [
                    'action' => $a->action,
                    'notes' => $a->notes,
                    'acted_at' => $a->acted_at?->toIso8601String(),
                    'approver' => [
                        'id' => $a->approver->id,
                        'name' => $a->approver->resident?->full_name ?? 'RT',
                    ],
                ])->toArray();
            }),
            'documents' => $this->when($this->relationLoaded('documents'), function () {
                return $this->documents->map(fn ($d) => [
                    'id' => $d->id,
                    'document_type' => $d->document_type,
                    'file_name' => $d->file_name,
                    'mime_type' => $d->mime_type,
                    'file_size' => $d->file_size,
                ])->toArray();
            }),
            'signatures' => $this->when($this->relationLoaded('signatures'), function () {
                return $this->signatures->map(fn ($s) => [
                    'signed_at' => $s->signed_at?->toIso8601String(),
                    'signer' => [
                        'id' => $s->signer->id,
                        'name' => $s->signer->resident?->full_name ?? 'RT',
                    ],
                ])->toArray();
            }),
            'stamps' => $this->when($this->relationLoaded('stamps'), function () {
                return $this->stamps->map(fn ($s) => [
                    'stamped_at' => $s->stamped_at?->toIso8601String(),
                    'stamper' => [
                        'id' => $s->stamper->id,
                        'name' => $s->stamper->resident?->full_name ?? 'RT',
                    ],
                ])->toArray();
            }),
        ];
    }
}
