<?php

namespace App\Models;

use App\Support\LandingPageDesigns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LandingPage extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'content'   => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    /**
     * Admin URLs use id (stable when slug changes). Still accept slug for old bookmarks.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        if ($field !== null) {
            return $this->where($field, $value)->first();
        }

        return $this->where($this->getRouteKeyName(), $value)->first()
            ?? $this->where('slug', $value)->first();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function designLabel(): string
    {
        return LandingPageDesigns::DESIGNS[$this->design]['label'] ?? ucfirst($this->design);
    }

    public function contentGet(string $key, mixed $default = null): mixed
    {
        $content = $this->content ?? [];

        return $content[$key] ?? $default;
    }

    public function sectionVisible(string $section): bool
    {
        $sections = $this->content['_sections'] ?? [];
        if (! array_key_exists($section, $sections)) {
            return true;
        }

        return (bool) $sections[$section];
    }

    public function mediaUrl(?string $path): string
    {
        if (! $path) {
            return '';
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '//')) {
            return $path;
        }
        // Public theme / landing assets (not on the storage disk)
        if (str_starts_with($path, 'landing/') || str_starts_with($path, 'theme/') || str_starts_with($path, 'uploads/')) {
            return asset(ltrim($path, '/'));
        }

        return image_url($path);
    }

    public function publicUrl(bool $preview = false): string
    {
        $url = route('landing.show', $this->slug, absolute: false);

        if ($preview || ! $this->is_active) {
            $url .= (str_contains($url, '?') ? '&' : '?').'preview=1';
        }

        return $url;
    }

    public static function makeSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'landing-page';
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
