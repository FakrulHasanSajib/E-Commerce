<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CouponUser extends Model
{
    /**
     * Disable default timestamps.
     */
    public $timestamps = false;

    /**
     * Define custom table name.
     */
    protected $table = 'coupon_user';

    /**
     * Fillable attributes.
     */
    protected $fillable = [
        'coupon_id',
        'user_id',
        'used_at',
    ];

    /**
     * Casts.
     */
    protected $casts = [
        'used_at' => 'datetime',
    ];
}
