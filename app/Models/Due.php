<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Due extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'amount',
        'frequency',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function dueBills(): HasMany
    {
        return $this->hasMany(DueBill::class);
    }
}
