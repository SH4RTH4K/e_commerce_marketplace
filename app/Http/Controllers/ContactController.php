<?php

namespace App\Http\Controllers;

use App\Models\ContactFormField;
use App\Models\ContactMessage;
use App\Models\ContactMessageValue;
use App\Support\PublicUploader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $fields = ContactFormField::active()->ordered()->get();
        if ($fields->isEmpty()) {
            return back()->withErrors(['contact' => 'The contact form is not available right now.']);
        }

        $rules = [];
        $attributes = [];
        foreach ($fields as $field) {
            $name = 'fields.' . $field->key;
            $attributes[$name] = $field->label;
            if ($field->type === 'file' && $request->filled('fields.' . $field->key . '_b64')) {
                // Base64 fallback present — skip multipart "file" rule (cPanel temp-dir workaround).
                $rules[$name] = ['nullable'];
            } else {
                $rules[$name] = $this->rulesForField($field);
            }
        }

        $validated = $request->validate($rules, [], $attributes);
        $input = $validated['fields'] ?? [];

        foreach ($fields as $field) {
            if ($field->type !== 'file' || ! $field->is_required) {
                continue;
            }
            $hasMultipart = $request->file('fields.' . $field->key) instanceof \Illuminate\Http\UploadedFile
                && $request->file('fields.' . $field->key)->isValid();
            $hasB64 = $request->filled('fields.' . $field->key . '_b64');
            if (! $hasMultipart && ! $hasB64) {
                throw ValidationException::withMessages([
                    'fields.' . $field->key => 'The ' . $field->label . ' field is required.',
                ]);
            }
        }

        $message = ContactMessage::create([
            'status'     => ContactMessage::STATUS_NEW,
            'name'       => $this->stringFrom($input, 'name'),
            'email'      => $this->stringFrom($input, 'email'),
            'phone'      => $this->stringFrom($input, 'phone'),
            'subject'    => $this->stringFrom($input, 'subject'),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);

        $pos = 0;
        foreach ($fields as $field) {
            $value = null;
            $filePath = null;
            $fileName = null;

            if ($field->type === 'file') {
                $path = PublicUploader::storeFromRequest($request, 'fields.' . $field->key, 'contact', 'bin');
                if ($path) {
                    $filePath = $path;
                    $fileName = (string) $request->input('fields.' . $field->key . '_name', basename($path));
                    $value = $fileName;
                }
            } elseif ($field->type === 'checkbox') {
                $value = ! empty($input[$field->key]) ? '1' : '0';
            } else {
                $raw = $input[$field->key] ?? null;
                $value = is_string($raw) ? trim($raw) : (is_scalar($raw) ? (string) $raw : null);
            }

            ContactMessageValue::create([
                'contact_message_id' => $message->id,
                'field_key'          => $field->key,
                'field_label'        => $field->label,
                'field_type'         => $field->type,
                'value'              => $value,
                'file_path'          => $filePath,
                'file_name'          => $fileName,
                'position'           => $pos++,
            ]);
        }

        $this->notifyAdmin($message->fresh('values'));

        return back()->with('status', 'Thanks — your message was sent. We will get back to you soon.');
    }

    private function rulesForField(ContactFormField $field): array
    {
        $rules = [$field->is_required ? 'required' : 'nullable'];

        return match ($field->type) {
            'email' => array_merge($rules, ['email', 'max:180']),
            'tel' => array_merge($rules, ['string', 'max:60']),
            'text', 'select' => array_merge($rules, ['string', 'max:255']),
            'textarea' => array_merge($rules, ['string', 'max:5000']),
            'checkbox' => $field->is_required ? ['accepted'] : ['nullable'],
            'file' => array_merge($rules, [
                'file',
                'max:' . max(1, (int) ($field->max_size_kb ?: 4096)),
            ]),
            default => array_merge($rules, ['string', 'max:255']),
        };
    }

    private function stringFrom(array $input, string $key): ?string
    {
        $value = trim((string) ($input[$key] ?? ''));

        return $value !== '' ? $value : null;
    }

    private function notifyAdmin(ContactMessage $message): void
    {
        $to = trim((string) setting('contact_email', ''));
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            configure_mail_from_settings();
            $lines = [
                'New contact form submission #' . $message->id,
                'From: ' . ($message->name ?: '—') . ' <' . ($message->email ?: 'no-email') . '>',
                'Phone: ' . ($message->phone ?: '—'),
                'Subject: ' . $message->displaySubject(),
                '',
            ];
            foreach ($message->values as $row) {
                $lines[] = $row->field_label . ': ' . $row->displayValue();
            }
            $lines[] = '';
            $lines[] = 'View in admin: ' . route('admin.contact-messages.show', $message);

            Mail::raw(implode("\n", $lines), function ($mail) use ($to, $message) {
                $mail->to($to)->subject('Contact: ' . $message->displaySubject());
            });
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
