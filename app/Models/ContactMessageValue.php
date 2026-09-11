<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactMessageValue extends Model
{
    protected $guarded = [];

    public function message(): BelongsTo
    {
        return $this->belongsTo(ContactMessage::class, 'contact_message_id');
    }

    public function isFile(): bool
    {
        return $this->field_type === 'file' && filled($this->file_path);
    }

    public function isImage(): bool
    {
        if (! $this->isFile()) {
            return false;
        }

        $name = strtolower((string) ($this->file_name ?: $this->file_path));
        $ext = pathinfo($name, PATHINFO_EXTENSION);

        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true);
    }

    public function fileUrl(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return asset(ltrim($this->file_path, '/'));
    }

    public function displayValue(): string
    {
        if ($this->isFile()) {
            return (string) ($this->file_name ?: basename((string) $this->file_path));
        }

        if ($this->field_type === 'checkbox') {
            return filter_var($this->value, FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No';
        }

        return (string) ($this->value ?? '');
    }
}
