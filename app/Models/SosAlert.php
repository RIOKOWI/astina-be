<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SosAlert extends Model
{
    use HasFactory;
    protected $fillable = [
        'triggered_by',
        'latitude',
        'longitude',
        'location_text',
        'status',
        'triggered_at',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'triggered_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function triggerer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SosResponse::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
