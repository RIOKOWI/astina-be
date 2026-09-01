<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'resident_id',
        'phone',
        'email',
        'password',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withPivot('created_at');
    }

    public function hasRole(string $roleCode): bool
    {
        return $this->roles->contains('code', $roleCode);
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function createdActivities(): HasMany
    {
        return $this->hasMany(Activity::class, 'created_by');
    }

    public function activityReads(): HasMany
    {
        return $this->hasMany(ActivityRead::class);
    }

    public function submittedLetters(): HasMany
    {
        return $this->hasMany(Letter::class, 'submitted_by');
    }

    public function letterApprovals(): HasMany
    {
        return $this->hasMany(LetterApproval::class, 'approved_by');
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(Signature::class, 'signed_by');
    }

    public function stamps(): HasMany
    {
        return $this->hasMany(Stamp::class, 'stamped_by');
    }

    public function approvedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'approved_by');
    }

    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'created_by');
    }

    public function assignedComplaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'assigned_to');
    }

    public function complaintComments(): HasMany
    {
        return $this->hasMany(ComplaintComment::class);
    }

    public function triggeredSosAlerts(): HasMany
    {
        return $this->hasMany(SosAlert::class, 'triggered_by');
    }

    public function resolvedSosAlerts(): HasMany
    {
        return $this->hasMany(SosAlert::class, 'resolved_by');
    }

    public function sosResponses(): HasMany
    {
        return $this->hasMany(SosResponse::class);
    }

    public function assetMovements(): HasMany
    {
        return $this->hasMany(AssetMovement::class, 'created_by');
    }
}
