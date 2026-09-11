@extends('layouts.admin')
@section('title', 'Contact Form')

@section('content')
<div class="space-y-6">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h2 class="text-xl font-bold text-ink">Contact form fields</h2>
      <p class="text-sm text-neutral-500 mt-1">Default fields stay available. Add extra fields (including file uploads) when needed.</p>
    </div>
    <a href="{{ route('admin.contact-fields.create') }}" class="btn-primary">+ Add field</a>
  </div>

  <div class="card overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="text-left text-neutral-500 bg-neutral-50">
        <tr>
          <th class="px-5 py-3 font-medium">Pos</th>
          <th class="px-5 py-3 font-medium">Label</th>
          <th class="px-5 py-3 font-medium">Type</th>
          <th class="px-5 py-3 font-medium">Required</th>
          <th class="px-5 py-3 font-medium">Status</th>
          <th class="px-5 py-3 font-medium text-right">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-neutral-100">
        @forelse($fields as $field)
          <tr class="hover:bg-neutral-50 {{ $field->is_active ? '' : 'opacity-60' }}">
            <td class="px-5 py-3 text-neutral-400">{{ $field->position }}</td>
            <td class="px-5 py-3">
              <p class="font-semibold text-ink">{{ $field->label }}</p>
              <p class="text-xs text-neutral-400 font-mono">{{ $field->key }}
                @if($field->is_system)<span class="ml-1 text-[10px] uppercase tracking-wide bg-neutral-100 text-neutral-500 px-1.5 py-0.5 rounded">Default</span>@endif
              </p>
            </td>
            <td class="px-5 py-3">{{ $field->typeLabel() }}</td>
            <td class="px-5 py-3">{{ $field->is_required ? 'Yes' : 'No' }}</td>
            <td class="px-5 py-3">
              @if($field->is_active)
                <span class="px-2 py-1 text-xs rounded-lg bg-emerald-100 text-emerald-700">Visible</span>
              @else
                <span class="px-2 py-1 text-xs rounded-lg bg-neutral-100 text-neutral-600">Hidden</span>
              @endif
            </td>
            <td class="px-5 py-3 text-right whitespace-nowrap">
              <form method="POST" action="{{ route('admin.contact-fields.toggle', $field) }}" class="inline">@csrf @method('PATCH')
                <button class="px-2 py-1 text-xs rounded bg-amber-50 text-amber-700 border border-amber-200">{{ $field->is_active ? 'Hide' : 'Show' }}</button>
              </form>
              <a href="{{ route('admin.contact-fields.edit', $field) }}" class="px-2 py-1 text-xs rounded bg-neutral-100 text-neutral-600">Edit</a>
              @unless($field->is_system)
                <form method="POST" action="{{ route('admin.contact-fields.destroy', $field) }}" class="inline" onsubmit="return confirm('Remove this extra field?')">@csrf @method('DELETE')
                  <button class="px-2 py-1 text-xs rounded bg-red-50 text-red-600 border border-red-200">Delete</button>
                </form>
              @endunless
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="px-5 py-12 text-center text-neutral-400">No fields yet. Run the seeder or add a field.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
