<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Household extends Model
{
    protected $fillable = [
        'no_kk',
        'head_resident_id',
        'address',
        'rt',
        'rw',
        'postal_code',
        'status',
    ];

    public function headResident(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'head_resident_id');
    }

    public function residents(): BelongsToMany
    {
        return $this->belongsToMany(Resident::class, 'resident_households')
            ->withPivot('relationship', 'joined_at', 'left_at', 'is_current')
            ->withTimestamps();
    }
}
