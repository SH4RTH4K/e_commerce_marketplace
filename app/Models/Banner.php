<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    public const PLACEMENTS = [
        'hero' => 'Hero — homepage slider slides (add multiple; ordered by position)',
        'middle' => 'Middle Banner — replacing coupons section on homepage',
    ];

    public const STYLES = [
        'brand'  => 'Blue (brand)',
        'amber'  => 'Amber / gold',
        'rose'   => 'Rose / pink',
        'accent' => 'Accent gradient',
    ];

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePlacement(Builder $query, string $placement): Builder
    {
        return $query->where('placement', $placement);
    }

    public function placementLabel(): string
    {
        return explode(' — ', self::PLACEMENTS[$this->placement] ?? $this->placement)[0];
    }

    public function styleLabel(): string
    {
        return self::STYLES[$this->style] ?? $this->style;
    }

    public function linkHref(): string
    {
        if (! $this->link_url) {
            return route('shop');
        }

        return str_starts_with($this->link_url, 'http') ? $this->link_url : url($this->link_url);
    }

    public function imageUrl(): string
    {
        return image_url($this->image, $this->title ?? 'banner');
    }
}
