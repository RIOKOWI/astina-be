<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Resident extends Model
{
    use HasFactory;

    protected $fillable = [
        'nik',
        'no_kk',
        'full_name',
        'birth_place',
        'birth_date',
        'gender',
        'religion',
        'marital_status',
        'occupation',
        'phone',
        'email',
        'status',
        'joined_at',
        'left_at',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'joined_at' => 'date',
            'left_at' => 'date',
        ];
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function headedHousehold(): HasOne
    {
        return $this->hasOne(Household::class, 'head_resident_id');
    }

    public function households(): BelongsToMany
    {
        return $this->belongsToMany(Household::class, 'resident_households')
            ->withPivot('relationship', 'joined_at', 'left_at', 'is_current')
            ->withTimestamps();
    }

    public function dueBills(): HasMany
    {
        return $this->hasMany(DueBill::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function letters(): HasMany
    {
        return $this->hasMany(Letter::class);
    }
}
