<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stamp extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'letter_id',
        'stamped_by',
        'stamp_path',
        'stamped_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'stamped_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    public function stamper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'stamped_by');
    }
}
