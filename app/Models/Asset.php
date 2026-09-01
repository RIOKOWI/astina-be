<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    protected $fillable = [
        'code',
        'name',
        'category',
        'description',
        'quantity',
        'unit',
        'purchase_price',
        'purchase_date',
        'condition',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'purchase_price' => 'decimal:2',
            'purchase_date' => 'date',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(AssetMovement::class);
    }
}
