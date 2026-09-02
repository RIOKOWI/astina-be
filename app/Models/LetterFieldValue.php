<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LetterFieldValue extends Model
{
    use HasFactory;
    protected $fillable = [
        'letter_id',
        'letter_field_id',
        'value',
    ];

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    public function letterField(): BelongsTo
    {
        return $this->belongsTo(LetterField::class);
    }
}
