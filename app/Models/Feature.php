<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Feature extends Model
{
    protected static function booted(): void
    {
        static::saved(fn () => Cache::store('file')->forget('storefront.home.props.v1'));
        static::deleted(fn () => Cache::store('file')->forget('storefront.home.props.v1'));
    }

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
