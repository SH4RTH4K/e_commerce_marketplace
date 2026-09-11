<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactMessage extends Model
{
    public const STATUS_NEW = 'new';
    public const STATUS_READ = 'read';
    public const STATUS_ARCHIVED = 'archived';

    protected $guarded = [];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(ContactMessageValue::class)->orderBy('position')->orderBy('id');
    }

    public function scopeNew(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_NEW);
    }

    public function markRead(): void
    {
        if ($this->status === self::STATUS_NEW) {
            $this->update([
                'status'  => self::STATUS_READ,
                'read_at' => now(),
            ]);
        }
    }

    public function archive(): void
    {
        $this->update([
            'status'  => self::STATUS_ARCHIVED,
            'read_at' => $this->read_at ?? now(),
        ]);
    }

    public function displaySubject(): string
    {
        $subject = trim((string) $this->subject);
        if ($subject !== '') {
            return $subject;
        }

        $message = $this->values->firstWhere('field_key', 'message');
        $preview = trim((string) ($message?->value ?? ''));
        if ($preview !== '') {
            return \Illuminate\Support\Str::limit($preview, 60);
        }

        return 'Contact inquiry #' . $this->id;
    }
}
