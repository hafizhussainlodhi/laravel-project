<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Nova\Actions\Actionable;

class UserCarrier extends Model
{
    use HasFactory, Actionable;

    protected $fillable = [
        'user_id',
        'carrier_id',
        'rate',
        'blocked',
    ];
    
    protected $casts = [
        'blocked' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function carrier()
    {
        return $this->belongsTo(Carrier::class, 'carrier_id');
    }
}
