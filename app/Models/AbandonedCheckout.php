<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbandonedCheckout extends Model
{
    protected $guarded = [];

    protected $casts = [
        'cart_items' => 'json',
        'cart_total' => 'decimal:2',
        'is_recovered' => 'boolean',
        'last_active_at' => 'datetime',
    ];
}
