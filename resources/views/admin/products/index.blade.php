@extends('layouts.admin')
@section('title', 'Products')

@section('content')
<div class="space-y-6">
  <div class="flex items-center justify-between gap-3">
    <h2 class="text-xl font-bold">Products</h2>
    <a href="{{ route('admin.products.create') }}" class="btn-primary shrink-0">+ New product</a>
  </div>

  <div class="card">
    <form method="GET" class="p-4 border-b border-gray-200 flex flex-wrap gap-3">
      <input type="text" name="q" value="{{ $q }}" placeholder="Search name or SKU…" class="flex-1 min-w-[160px] sm:min-w-[200px] border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40" />
      <select name="category" onchange="this.form.submit()" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <option value="">All categories</option>
        @foreach($categories as $cat)
          <option value="{{ $cat->id }}" @selected((string) $category === (string) $cat->id)>{{ $cat->name }}</option>
        @endforeach
      </select>
      <select name="status" onchange="this.form.submit()" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <option value="">All status</option>
        <option value="active" @selected($status === 'active')>Active</option>
        <option value="inactive" @selected($status === 'inactive')>Inactive</option>
      </select>
      <button class="btn-primary">Search</button>
    </form>

    <form id="productsBulkForm" method="POST" action="{{ route('admin.products.bulk') }}" data-bulk-form>
      @csrf
      <div data-bulk-bar class="px-4 py-3 border-b border-gray-200 bg-primary/5 flex flex-wrap items-center gap-3">
        <span class="text-sm font-medium text-ink"><span data-bulk-count>0</span> selected</span>
        <select name="bulk_action" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm bg-white">
          <option value="">Choose action…</option>
          <option value="activate">Set Active</option>
          <option value="deactivate">Set Inactive</option>
          <option value="delete" data-confirm="Delete :count selected product(s)? This cannot be undone.">Delete</option>
        </select>
        <button type="submit" data-bulk-apply class="btn-primary text-sm py-1.5" disabled>Apply</button>
      </div>
    </form>

    <div class="overflow-x-auto" data-bulk-scope="productsBulkForm">
      <table class="admin-data-table w-full text-sm">
        <thead class="text-left text-gray-500 bg-gray-50">
          <tr>
            <th class="admin-data-table__cell font-medium w-10">
              <input type="checkbox" form="productsBulkForm" data-bulk-select-all class="accent-primary h-4 w-4" title="Select all" aria-label="Select all" />
            </th>
            <th class="admin-data-table__cell font-medium">Product</th>
            <th class="admin-data-table__cell font-medium">Category</th>
            <th class="admin-data-table__cell font-medium">Price</th>
            <th class="admin-data-table__cell font-medium">Stock</th>
            <th class="admin-data-table__cell font-medium">Status</th>
            <th class="admin-data-table__cell font-medium text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @forelse($products as $product)
            <tr class="hover:bg-gray-50 {{ $product->is_published ? '' : 'opacity-70' }}">
              <td class="admin-data-table__cell">
                <input type="checkbox" form="productsBulkForm" name="ids[]" value="{{ $product->id }}" data-bulk-row class="accent-primary h-4 w-4" aria-label="Select {{ $product->name }}" />
              </td>
              <td class="admin-data-table__cell">
                <div class="admin-data-table__product">
                  <img src="{{ $product->imageUrl() }}" class="admin-data-table__thumb" alt="">
                  <div class="admin-data-table__product-meta">
                    <p class="font-medium leading-snug">{{ $product->name }}</p>
                    <p class="text-gray-400 text-xs mt-0.5">{{ $product->sku }}</p>
                  </div>
                </div>
              </td>
              <td class="admin-data-table__cell text-gray-600 whitespace-nowrap">{{ $product->category?->name }}</td>
              <td class="admin-data-table__cell whitespace-nowrap">
                <span class="admin-data-table__price">
                  <span>{{ money($product->price) }}</span>
                  @if($product->on_sale)
                    <span class="text-gray-400 line-through text-xs">{{ money($product->regular_price) }}</span>
                  @endif
                </span>
              </td>
              <td class="admin-data-table__cell whitespace-nowrap">{{ $product->stock_quantity }}</td>
              <td class="admin-data-table__cell">
                @if($product->is_published)
                  <span class="inline-block px-2 py-1 text-xs rounded-full bg-green-100 text-green-700 whitespace-nowrap">Active</span>
                @else
                  <span class="inline-block px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-500 whitespace-nowrap">Inactive</span>
                @endif
              </td>
              <td class="admin-data-table__cell text-right">
                <div class="admin-data-table__actions">
                  <form method="POST" action="{{ route('admin.products.toggle', $product) }}" class="inline">@csrf @method('PATCH')
                    <button type="submit" class="px-2.5 py-1 text-xs rounded {{ $product->is_published ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-green-50 text-green-700 border border-green-200' }}">
                      {{ $product->is_published ? 'Set Inactive' : 'Set Active' }}
                    </button>
                  </form>
                  <a href="{{ route('admin.products.edit', $product) }}" class="px-2.5 py-1 text-xs rounded bg-gray-100 text-gray-600">Edit</a>
                  <form method="POST" action="{{ route('admin.products.destroy', $product) }}" class="inline" onsubmit="return confirm('Delete this product?')">@csrf @method('DELETE')<button class="px-2.5 py-1 text-xs rounded bg-red-50 text-red-600 border border-red-200">Delete</button></form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="admin-data-table__cell py-10 text-center text-gray-400">No products found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="p-4 border-t border-gray-200">{{ $products->links() }}</div>
  </div>
</div>
@endsection
