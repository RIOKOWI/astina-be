<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Complaint extends Model
{
    protected $fillable = [
        'resident_id',
        'assigned_to',
        'reference_no',
        'title',
        'description',
        'category',
        'status',
        'rejection_reason',
        'submitted_at',
        'approved_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ComplaintAttachment::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ComplaintComment::class);
    }
}
