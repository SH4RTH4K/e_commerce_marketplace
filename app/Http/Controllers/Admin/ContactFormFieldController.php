<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use App\Http\Controllers\Controller;
use App\Models\ContactFormField;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactFormFieldController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/ContactFields/Index', [
            'fields' => ContactFormField::ordered()->get(),
            'types'  => ContactFormField::TYPES,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/ContactFields/Form', [
            'field' => new ContactFormField([
                'type'        => 'text',
                'is_required' => false,
                'is_active'   => true,
                'position'    => (int) ContactFormField::max('position') + 1,
                'max_size_kb' => 4096,
                'accept'      => '.pdf,.jpg,.jpeg,.png,.doc,.docx',
            ]),
            'types' => ContactFormField::TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        unset($data['options_text']);
        $data['key'] = ContactFormField::uniqueKey($data['label']);
        $data['is_system'] = false;
        $data['is_active'] = $request->boolean('is_active', true);
        $data['is_required'] = $request->boolean('is_required');
        $data['options'] = $this->parseOptions($request, $data['type']);

        ContactFormField::create($data);

        return redirect()->route('admin.contact-fields.index')->with('status', 'Field added to the contact form.');
    }

    public function edit(ContactFormField $contact_field)
    {
        return Inertia::render('Admin/ContactFields/Form', [
            'field' => $contact_field,
            'types' => ContactFormField::TYPES,
        ]);
    }

    public function update(Request $request, ContactFormField $contact_field)
    {
        $data = $this->validateData($request, $contact_field);
        unset($data['options_text']);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['is_required'] = $request->boolean('is_required');
        $data['options'] = $this->parseOptions($request, $data['type'] ?? $contact_field->type);

        // System fields keep their key and type constraints for reliable inbox columns.
        if ($contact_field->is_system) {
            unset($data['type']);
            if (in_array($contact_field->key, ['name', 'email', 'message'], true)) {
                $data['is_required'] = true;
            }
        }

        $contact_field->update($data);

        return redirect()->route('admin.contact-fields.index')->with('status', 'Field updated.');
    }

    public function destroy(ContactFormField $contact_field)
    {
        if ($contact_field->is_system) {
            return back()->withErrors(['contact_field' => 'Default fields cannot be deleted. You can deactivate optional ones instead.']);
        }

        $contact_field->delete();

        return redirect()->route('admin.contact-fields.index')->with('status', 'Extra field removed.');
    }

    public function toggle(ContactFormField $contact_field)
    {
        if ($contact_field->is_system && in_array($contact_field->key, ['name', 'email', 'message'], true) && $contact_field->is_active) {
            return back()->withErrors(['contact_field' => 'Name, Email, and Message stay active by default.']);
        }

        $contact_field->update(['is_active' => ! $contact_field->is_active]);

        return back()->with('status', $contact_field->is_active ? 'Field is now visible on the form.' : 'Field hidden from the form.');
    }

    private function validateData(Request $request, ?ContactFormField $field = null): array
    {
        $types = array_keys(ContactFormField::TYPES);

        return $request->validate([
            'label'       => ['required', 'string', 'max:120'],
            'type'        => ['required', Rule::in($types)],
            'placeholder' => ['nullable', 'string', 'max:180'],
            'help_text'   => ['nullable', 'string', 'max:255'],
            'position'    => ['nullable', 'integer', 'min:0'],
            'accept'      => ['nullable', 'string', 'max:120'],
            'max_size_kb' => ['nullable', 'integer', 'min:64', 'max:10240'],
            'options_text'=> ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function parseOptions(Request $request, string $type): ?array
    {
        if ($type !== 'select') {
            return null;
        }

        $lines = preg_split('/\r\n|\r|\n/', (string) $request->input('options_text', '')) ?: [];
        $opts = array_values(array_filter(array_map('trim', $lines)));

        return $opts ?: null;
    }
}
