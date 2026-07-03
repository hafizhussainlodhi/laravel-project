<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Nova\Actions\Actionable;

class Carrier extends Model
{
    use HasFactory, SoftDeletes, Actionable;

    protected $fillable = [
        'name',
        'image',
        'price',
        'is_active',
        'cost',
    ];

    public function numbers()
    {
        return $this->hasMany(Number::class, 'carrier_id');
    }

    public function scopeIsActive($query)
    {
        return $query->where('is_active', true);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, UserCarrier::class, 'carrier_id', 'user_id')
            ->withPivot('rate', 'blocked')
            ->withTimestamps();
    }


    // aditional method 

    // Buyers relation
    public function buyers()
    {
        return $this->belongsToMany(User::class, 'user_carriers')
            ->where('role', \App\Models\User::USER_ROLE) // buyer
            ->withPivot(['rate', 'blocked']);
    }

    // Sellers relation
    public function sellers()
    {
        return $this->belongsToMany(User::class, 'user_carriers')
            ->where('role', \App\Models\User::SELLER_ROLE) // seller
            ->withPivot(['rate', 'blocked']);
    }
}
