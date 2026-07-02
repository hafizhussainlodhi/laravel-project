<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReferralLink extends Model
{
    protected $fillable = [
        'user_id',
        'code',
        'is_used',
        'used_at',
        'expires_at',
    ];

    protected $casts = [
        'is_used'    => 'boolean',
        'used_at'    => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return ! $this->is_used && ! $this->isExpired();
    }

    // Auto-generate code + expiry on create
    protected static function booted()
    {
        static::creating(function ($link) {
            // Only set if not already provided (Action already sets these, so no conflict)
            if (empty($link->code)) {
                $link->code = Str::random(16);
            }
            if (empty($link->expires_at)) {
                $link->expires_at = now()->addWeek();
            }
        });
    }
}