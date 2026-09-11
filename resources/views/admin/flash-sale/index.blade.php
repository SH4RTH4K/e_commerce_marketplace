@extends('layouts.admin')
@section('title', 'Flash Sale')

@section('content')
@php
  $endsLocal = !empty($endsAt)
    ? \Illuminate\Support\Str::of($endsAt)->replace(' ', 'T')->substr(0, 16)
    : '';
@endphp
<div class="space-y-6">
  <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
    <div>
      <h2 class="text-xl font-bold">Flash Sale</h2>
      <p class="text-sm text-gray-500 mt-1">Choose which products appear in the homepage Flash Sale section and set when it ends.</p>
      <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mt-2">
        Homepage shows up to <strong>{{ $homepageLimit }}</strong> Flash Sale products (by the order below). Extra products stay in this list but are not shown on the storefront until you reorder or remove others.
      </p>
    </div>
    <a href="{{ route('home') }}" target="_blank" class="text-sm font-semibold text-brand-700 hover:underline shrink-0">View on storefront →</a>
  </div>

  @if(session('status'))
    <div class="rounded-xl bg-brand-50 border border-brand-200 text-brand-800 text-sm px-4 py-3">{{ session('status') }}</div>
  @endif
  <div id="flashReorderFeedback" class="hidden rounded-xl text-sm px-4 py-3 border"></div>

  <form method="POST" action="{{ route('admin.flash-sale.ends-at') }}" class="card p-4 sm:p-5">
    @csrf
    @method('PUT')
    <div class="flex flex-col sm:flex-row sm:items-end gap-3">
      <div class="flex-1 min-w-0">
        <label class="lbl">Flash sale ends at</label>
        <input type="datetime-local" name="flash_sale_ends_at" class="inp" value="{{ old('flash_sale_ends_at', $endsLocal) }}" />
        <p class="text-xs text-gray-400 mt-1">Shown as the countdown on the homepage. Leave blank to hide the countdown.</p>
      </div>
      <button type="submit" class="btn-primary shrink-0">Save end time</button>
    </div>
  </form>

  <div class="card overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100">
      <h3 class="font-semibold text-ink">In Flash Sale ({{ $flashProducts->count() }})</h3>
      <p class="text-xs text-gray-500 mt-0.5">Drag to reorder products shown in the homepage Flash Sale section.</p>
    </div>

    @if($flashProducts->isEmpty())
      <div class="px-5 py-12 text-center text-gray-400 text-sm">No flash sale products yet. Add some from the list below.</div>
    @else
            <form id="flashRemoveBulkForm" method="POST" action="{{ route('admin.flash-sale.bulk') }}" data-bulk-form>
        @csrf
        <input type="hidden" name="bulk_action" value="remove" />
        <div data-bulk-bar class="px-4 py-3 border-b border-gray-100 bg-primary/5 flex flex-wrap items-center gap-3">
          <span class="text-sm font-medium text-ink"><span data-bulk-count>0</span> selected</span>
          <button type="submit" data-bulk-apply class="px-3 py-1.5 text-xs rounded-lg bg-red-50 text-red-700 border border-red-200 font-semibold" disabled
            data-confirm="Remove :count product(s) from Flash Sale?">Remove selected</button>
        </div>
      </form>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="text-left text-gray-500 bg-gray-50">
            <tr>
                            <th class="px-5 py-3 font-medium w-10">
                <input type="checkbox" form="flashRemoveBulkForm" data-bulk-select-all class="accent-primary h-4 w-4" title="Select all" aria-label="Select all" />
              </th>
<th class="px-5 py-3 font-medium w-12"></th>
              <th class="px-5 py-3 font-medium w-16">#</th>
              <th class="px-5 py-3 font-medium">Product</th>
              <th class="px-5 py-3 font-medium">Price</th>
              <th class="px-5 py-3 font-medium">Stock</th>
              <th class="px-5 py-3 font-medium text-right">Actions</th>
            </tr>
          </thead>
          <tbody id="flashSaleList" class="divide-y divide-gray-100">
            @foreach($flashProducts as $i => $product)
              <tr class="hover:bg-gray-50 bg-white" data-id="{{ $product->id }}">
                                <td class="px-5 py-3">
                  <input type="checkbox" form="flashRemoveBulkForm" name="ids[]" value="{{ $product->id }}" data-bulk-row class="accent-primary h-4 w-4" aria-label="Select {{ $product->name }}" />
                </td>
<td class="px-3 py-3 w-12">
                  <button type="button" class="flash-drag-handle inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 text-gray-500 hover:bg-brand-50 hover:text-brand-700 hover:border-brand-200 cursor-grab active:cursor-grabbing" title="Drag to reorder" aria-label="Drag to reorder">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                      <circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/>
                      <circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/>
                      <circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/>
                    </svg>
                  </button>
                </td>
                <td class="px-5 py-3">
                  <span class="flash-pos inline-flex h-8 w-8 items-center justify-center rounded-lg bg-accent-500/10 text-accent-600 font-bold text-xs">{{ $i + 1 }}</span>
                </td>
                <td class="px-5 py-3">
                  <div class="flex items-center gap-3">
                    <img src="{{ $product->imageUrl() }}" class="h-11 w-11 object-cover rounded-lg bg-gray-100" alt="">
                    <div>
                      <p class="font-medium">{{ $product->name }}</p>
                      <p class="text-xs text-gray-400">{{ $product->category?->name }} &middot; {{ $product->sku }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-5 py-3">
                  <span class="font-semibold {{ $product->on_sale ? 'text-accent-600' : '' }}">{{ money($product->price) }}</span>
                  @if($product->on_sale)<span class="text-xs text-gray-400 line-through ml-1">{{ money($product->regular_price) }}</span>@endif
                </td>
                <td class="px-5 py-3">{{ $product->stock_quantity }}</td>
                <td class="px-5 py-3 text-right whitespace-nowrap">
                  <a href="{{ route('admin.products.edit', $product) }}" class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-600">Edit</a>
                  <form method="POST" action="{{ route('admin.flash-sale.remove', $product) }}" class="inline" onsubmit="return confirm('Remove from Flash Sale?')">
                    @csrf @method('DELETE')
                    <button class="px-2 py-1 text-xs rounded bg-red-50 text-red-600 border border-red-200">Remove</button>
                  </form>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>

  <div class="card overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <h3 class="font-semibold text-ink">Add products</h3>
        <p class="text-xs text-gray-500 mt-0.5">Published products not already in the flash sale.</p>
      </div>
      <form method="GET" class="flex gap-2">
        <input type="text" name="q" value="{{ $q }}" placeholder="Search name, SKU, brand…" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-200 min-w-[200px]" />
        <button class="btn-primary">Search</button>
      </form>
    </div>
        <form id="flashAddBulkForm" method="POST" action="{{ route('admin.flash-sale.bulk') }}" data-bulk-form>
      @csrf
      <input type="hidden" name="bulk_action" value="add" />
      <div data-bulk-bar class="px-4 py-3 border-b border-gray-100 bg-primary/5 flex flex-wrap items-center gap-3">
        <span class="text-sm font-medium text-ink"><span data-bulk-count>0</span> selected</span>
        <button type="submit" data-bulk-apply class="px-3 py-1.5 text-xs rounded-lg bg-accent-500 text-white font-semibold" disabled>Add selected to Flash Sale</button>
      </div>
    </form>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="text-left text-gray-500 bg-gray-50">
          <tr>
                        <th class="px-5 py-3 font-medium w-10">
              <input type="checkbox" form="flashAddBulkForm" data-bulk-select-all class="accent-primary h-4 w-4" title="Select all" aria-label="Select all" />
            </th>
<th class="px-5 py-3 font-medium">Product</th>
            <th class="px-5 py-3 font-medium">Price</th>
            <th class="px-5 py-3 font-medium">Stock</th>
            <th class="px-5 py-3 font-medium text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @forelse($available as $product)
            <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                <input type="checkbox" form="flashAddBulkForm" name="ids[]" value="{{ $product->id }}" data-bulk-row class="accent-primary h-4 w-4" aria-label="Select {{ $product->name }}" />
              </td>
<td class="px-5 py-3">
                <div class="flex items-center gap-3">
                  <img src="{{ $product->imageUrl() }}" class="h-11 w-11 object-cover rounded-lg bg-gray-100" alt="">
                  <div>
                    <p class="font-medium">{{ $product->name }}</p>
                    <p class="text-xs text-gray-400">{{ $product->category?->name }} &middot; {{ $product->sku }}</p>
                  </div>
                </div>
              </td>
              <td class="px-5 py-3">
                {{ money($product->price) }}
                @if($product->on_sale)<span class="text-xs text-gray-400 line-through ml-1">{{ money($product->regular_price) }}</span>@endif
              </td>
              <td class="px-5 py-3">{{ $product->stock_quantity }}</td>
              <td class="px-5 py-3 text-right">
                <form method="POST" action="{{ route('admin.flash-sale.add', $product) }}" class="inline">
                  @csrf
                  <button class="px-3 py-1.5 text-xs rounded-lg bg-accent-500 text-white font-semibold hover:bg-accent-600">+ Add to Flash Sale</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="px-5 py-10 text-center text-gray-400">No matching products to add.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($available->hasPages())
      <div class="p-4 border-t border-gray-100">{{ $available->links() }}</div>
    @endif
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<script>
(function () {
  var list = document.getElementById('flashSaleList');
  var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  var feedback = document.getElementById('flashReorderFeedback');

  function showFeedback(ok, message) {
    if (!feedback) return;
    feedback.classList.remove('hidden', 'bg-brand-50', 'border-brand-200', 'text-brand-800', 'bg-red-50', 'border-red-200', 'text-red-700');
    if (ok) {
      feedback.classList.add('bg-brand-50', 'border-brand-200', 'text-brand-800');
    } else {
      feedback.classList.add('bg-red-50', 'border-red-200', 'text-red-700');
    }
    feedback.textContent = message;
  }

  /* ---- Drag reorder ---- */
  if (!list || typeof Sortable === 'undefined') return;

  var reorderUrl = @json(route('admin.flash-sale.reorder'));

  function renumber() {
    list.querySelectorAll('.flash-pos').forEach(function (el, i) {
      el.textContent = String(i + 1);
    });
  }

  function saveOrder() {
    var order = Array.from(list.querySelectorAll('tr[data-id]')).map(function (row) {
      return parseInt(row.getAttribute('data-id'), 10);
    });

    var body = new FormData();
    body.append('_method', 'PUT');
    order.forEach(function (id) { body.append('order[]', id); });

    fetch(reorderUrl, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrf,
      },
      body: body,
      credentials: 'same-origin',
    })
      .then(function (res) {
        return res.json().then(function (data) {
          if (!res.ok) throw data;
          return data;
        });
      })
      .then(function (data) {
        showFeedback(true, data.message || 'Order saved.');
      })
      .catch(function () {
        showFeedback(false, 'Could not save order. Refresh and try again.');
      });
  }

  Sortable.create(list, {
    handle: '.flash-drag-handle',
    animation: 150,
    ghostClass: 'opacity-40',
    chosenClass: 'bg-brand-50',
    dragClass: 'shadow-lg',
    onEnd: function () {
      renumber();
      saveOrder();
    },
  });
})();
</script>
@endpush
