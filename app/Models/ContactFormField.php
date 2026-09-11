<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContactFormField extends Model
{
    public const TYPES = [
        'text'     => 'Text',
        'email'    => 'Email',
        'tel'      => 'Phone',
        'textarea' => 'Long text',
        'select'   => 'Dropdown',
        'checkbox' => 'Checkbox',
        'file'     => 'File upload',
    ];

    public const SYSTEM_KEYS = ['name', 'email', 'phone', 'subject', 'message'];

    protected $guarded = [];

    protected $casts = [
        'options'     => 'array',
        'is_required' => 'boolean',
        'is_system'   => 'boolean',
        'is_active'   => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function optionList(): array
    {
        $opts = $this->options;
        if (! is_array($opts)) {
            return [];
        }

        return array_values(array_filter(array_map(fn ($o) => trim((string) $o), $opts)));
    }

    public static function uniqueKey(string $label, ?int $ignoreId = null): string
    {
        $base = Str::slug($label, '_') ?: 'field';
        $base = Str::limit($base, 50, '');
        $key = $base;
        $i = 2;
        while (static::where('key', $key)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $key = $base . '_' . $i++;
        }

        return $key;
    }
}
