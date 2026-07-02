<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Nova\Actions\Actionable;

class Setting extends Model
{
    use HasFactory, Actionable;

    const TEXT = 'TEXT';
    const LONG_TEXT = 'LONG_TEXT';

    const NUMBER_OF_DAYS_TO_EXPIRE = 'NUMBER_OF_DAYS_TO_EXPIRE';
    const ORDER_REFUND_TIME = 'ORDER_REFUND_TIME';
    const ORDER_REFUNDED_BY_HOURS = 'ORDER_REFUNDED_BY_HOURS';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'value',
        'type',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
    ];

    public static array $typesLabels = [
        self::TEXT => 'Text',
        self::LONG_TEXT => 'Long Text',
    ];

}
