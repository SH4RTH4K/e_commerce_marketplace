<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['key', 'name', 'description', 'permissions', 'is_system'])]
class Role extends Model
{
    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_system' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role', 'key');
    }

    /** @return list<string> */
    public static function permissionKeys(): array
    {
        $keys = [];
        foreach (User::PERMISSIONS as $group) {
            foreach (array_keys($group['items'] ?? []) as $key) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /** @return list<string> */
    public static function adminKeys(): array
    {
        return static::query()->pluck('key')->map(fn ($key) => (string) $key)->values()->all();
    }

    public static function findByKey(?string $key): ?self
    {
        return $key ? static::query()->where('key', $key)->first() : null;
    }
}
