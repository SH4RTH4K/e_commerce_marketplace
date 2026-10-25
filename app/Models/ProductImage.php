<?php

namespace App\Models;

use App\Jobs\WarmProductImageVariants;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::created(function (ProductImage $image): void {
            if (app()->runningUnitTests()) {
                return;
            }

            WarmProductImageVariants::dispatch($image->getKey())->afterCommit();
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function url(): string
    {
        return image_url($this->path, $this->alt ?? 'product');
    }
}
