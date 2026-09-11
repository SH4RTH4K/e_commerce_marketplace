@extends('layouts.admin')
@section('title', 'Categories')

@section('content')
<div class="space-y-6">
  <div class="flex items-center justify-between">
    <h2 class="text-xl font-bold">Categories</h2>
    <a href="{{ route('admin.categories.create') }}" class="btn-primary">+ New category</a>
  </div>

  <div class="card overflow-hidden">
    <form id="categoriesBulkForm" method="POST" action="{{ route('admin.categories.bulk') }}" data-bulk-form>
      @csrf
      <div data-bulk-bar class="px-4 py-3 border-b border-gray-200 bg-primary/5 flex flex-wrap items-center gap-3">
        <span class="text-sm font-medium text-ink"><span data-bulk-count>0</span> selected</span>
        <select name="bulk_action" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm bg-white">
          <option value="">Choose action…</option>
          <option value="activate">Set Active</option>
          <option value="deactivate">Set Hidden</option>
          <option value="delete" data-confirm="Delete :count selected categor(y/ies) and their products? This cannot be undone.">Delete</option>
        </select>
        <button type="submit" data-bulk-apply class="btn-primary text-sm py-1.5" disabled>Apply</button>
      </div>
    </form>

    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="text-left text-gray-500 bg-gray-50">
          <tr>
            <th class="px-5 py-3 font-medium w-10">
              <input type="checkbox" form="categoriesBulkForm" data-bulk-select-all class="accent-primary h-4 w-4" title="Select all" aria-label="Select all" />
            </th>
            <th class="px-5 py-3 font-medium">Category</th>
            <th class="px-5 py-3 font-medium">Slug</th>
            <th class="px-5 py-3 font-medium">Products</th>
            <th class="px-5 py-3 font-medium">Status</th>
            <th class="px-5 py-3 font-medium text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @forelse($categories as $cat)
            <tr class="hover:bg-gray-50">
              <td class="px-5 py-3">
                <input type="checkbox" form="categoriesBulkForm" name="ids[]" value="{{ $cat->id }}" data-bulk-row class="accent-primary h-4 w-4" aria-label="Select {{ $cat->name }}" />
              </td>
              <td class="px-5 py-3"><div class="flex items-center gap-3"><img src="{{ $cat->imageUrl() }}" class="h-10 w-10 object-cover rounded-lg bg-gray-100" alt=""><span class="font-medium">{{ $cat->icon }} {{ $cat->name }}</span></div></td>
              <td class="px-5 py-3 text-gray-500">{{ $cat->slug }}</td>
              <td class="px-5 py-3">{{ $cat->products_count }}</td>
              <td class="px-5 py-3">@if($cat->is_active)<span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-700">Active</span>@else<span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-500">Hidden</span>@endif</td>
              <td class="px-5 py-3 text-right whitespace-nowrap">
                <a href="{{ route('admin.categories.edit', $cat) }}" class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-600">Edit</a>
                <form method="POST" action="{{ route('admin.categories.destroy', $cat) }}" class="inline" onsubmit="return confirm('Delete category and its products?')">@csrf @method('DELETE')<button class="px-2 py-1 text-xs rounded bg-red-50 text-red-600 border border-red-200">Delete</button></form>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="px-5 py-10 text-center text-gray-400">No categories yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
