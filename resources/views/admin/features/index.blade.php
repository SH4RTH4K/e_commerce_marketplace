@extends('layouts.admin')
@section('title', 'Features')

@section('content')
<div class="space-y-6">
  <div class="flex items-center justify-between">
    <h2 class="text-xl font-bold">Features (homepage strip)</h2>
    <a href="{{ route('admin.features.create') }}" class="btn-primary">+ New feature</a>
  </div>

  <div class="card overflow-hidden">
    <form id="featuresBulkForm" method="POST" action="{{ route('admin.features.bulk') }}" data-bulk-form>
      @csrf
      <div data-bulk-bar class="px-4 py-3 border-b border-gray-200 bg-primary/5 flex flex-wrap items-center gap-3">
        <span class="text-sm font-medium text-ink"><span data-bulk-count>0</span> selected</span>
        <select name="bulk_action" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm bg-white">
          <option value="">Choose action…</option>
          <option value="activate">Set Active</option>
          <option value="deactivate">Set Hidden</option>
          <option value="delete" data-confirm="Delete :count selected feature(s)?">Delete</option>
        </select>
        <button type="submit" data-bulk-apply class="btn-primary text-sm py-1.5" disabled>Apply</button>
      </div>
    </form>

    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="text-left text-gray-500 bg-gray-50">
          <tr>
            <th class="px-5 py-3 font-medium w-10">
              <input type="checkbox" form="featuresBulkForm" data-bulk-select-all class="accent-primary h-4 w-4" title="Select all" aria-label="Select all" />
            </th>
            <th class="px-5 py-3 font-medium">Icon</th>
            <th class="px-5 py-3 font-medium">Title</th>
            <th class="px-5 py-3 font-medium">Subtitle</th>
            <th class="px-5 py-3 font-medium">Status</th>
            <th class="px-5 py-3 font-medium">Pos</th>
            <th class="px-5 py-3 font-medium text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @forelse($features as $feature)
            <tr class="hover:bg-gray-50">
              <td class="px-5 py-3">
                <input type="checkbox" form="featuresBulkForm" name="ids[]" value="{{ $feature->id }}" data-bulk-row class="accent-primary h-4 w-4" aria-label="Select {{ $feature->title }}" />
              </td>
              <td class="px-5 py-3"><svg class="h-6 w-6 text-primary" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $feature->icon }}"/></svg></td>
              <td class="px-5 py-3 font-medium">{{ $feature->title }}</td>
              <td class="px-5 py-3 text-gray-500">{{ $feature->subtitle }}</td>
              <td class="px-5 py-3">
                @if($feature->is_active)
                  <span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-700">Active</span>
                @else
                  <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-500">Hidden</span>
                @endif
              </td>
              <td class="px-5 py-3">{{ $feature->position }}</td>
              <td class="px-5 py-3 text-right whitespace-nowrap">
                <a href="{{ route('admin.features.edit', $feature) }}" class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-600">Edit</a>
                <form method="POST" action="{{ route('admin.features.destroy', $feature) }}" class="inline" onsubmit="return confirm('Delete feature?')">@csrf @method('DELETE')<button class="px-2 py-1 text-xs rounded bg-red-50 text-red-600 border border-red-200">Delete</button></form>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="px-5 py-10 text-center text-gray-400">No features yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
