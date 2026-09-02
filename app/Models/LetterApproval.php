<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LetterApproval extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $fillable = [
        'letter_id',
        'approved_by',
        'action',
        'notes',
        'acted_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'acted_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
