<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\Request;

class ContactMessageController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'new');
        if (! in_array($status, ['new', 'read', 'archived', 'all'], true)) {
            $status = 'new';
        }

        $query = ContactMessage::with('values')->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $term = trim((string) $request->input('q'));
        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('subject', 'like', "%{$term}%");
            });
        }

        $counts = [
            'new'      => ContactMessage::where('status', ContactMessage::STATUS_NEW)->count(),
            'read'     => ContactMessage::where('status', ContactMessage::STATUS_READ)->count(),
            'archived' => ContactMessage::where('status', ContactMessage::STATUS_ARCHIVED)->count(),
            'all'      => ContactMessage::count(),
        ];

        return Inertia::render('Admin/Messages/Index', [
            'messages' => $query->paginate(20)->withQueryString(),
            'status'   => $status,
            'q'        => $term,
            'counts'   => $counts,
        ]);
    }

    public function show(ContactMessage $contact_message)
    {
        $contact_message->load('values');
        $contact_message->markRead();

        return Inertia::render('Admin/Messages/Show', [
            'message' => $contact_message->fresh('values'),
        ]);
    }

    public function markRead(ContactMessage $contact_message)
    {
        $contact_message->markRead();

        return back()->with('status', 'Marked as read.');
    }

    public function archive(ContactMessage $contact_message)
    {
        $contact_message->archive();

        return back()->with('status', 'Message archived.');
    }

    public function destroy(ContactMessage $contact_message)
    {
        foreach ($contact_message->values as $value) {
            if ($value->file_path && str_starts_with($value->file_path, 'uploads/')) {
                $full = public_path($value->file_path);
                if (is_file($full)) {
                    @unlink($full);
                }
            }
        }

        $contact_message->delete();

        return redirect()->route('admin.contact-messages.index')->with('status', 'Message deleted.');
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'ids'         => ['required', 'array', 'min:1'],
            'ids.*'       => ['integer', 'exists:contact_messages,id'],
            'bulk_action' => ['required', 'in:read,archive,delete'],
        ]);

        $messages = ContactMessage::whereIn('id', $data['ids'])->get();
        $count = 0;

        foreach ($messages as $message) {
            if ($data['bulk_action'] === 'read') {
                $message->markRead();
                $count++;
            } elseif ($data['bulk_action'] === 'archive') {
                $message->archive();
                $count++;
            } else {
                $message->load('values');
                foreach ($message->values as $value) {
                    if ($value->file_path && str_starts_with($value->file_path, 'uploads/')) {
                        $full = public_path($value->file_path);
                        if (is_file($full)) {
                            @unlink($full);
                        }
                    }
                }
                $message->delete();
                $count++;
            }
        }

        $label = match ($data['bulk_action']) {
            'read'    => 'marked as read',
            'archive' => 'archived',
            default   => 'deleted',
        };

        return back()->with('status', "{$count} message(s) {$label}.");
    }
}
