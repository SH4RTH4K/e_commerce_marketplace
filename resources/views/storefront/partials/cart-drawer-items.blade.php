@foreach($items as $item)
  <div class="flex gap-3 py-4 border-b border-slate-100">
    <img src="{{ image_url($item->image, $item->name) }}" alt="{{ $item->name }}" class="h-16 w-16 rounded-xl object-cover bg-slate-100" loading="lazy">
    <div class="flex-1 min-w-0">
      <p class="text-sm font-semibold text-ink truncate">{{ $item->name }}</p>
      <p class="text-xs text-slate-500 mt-0.5">{{ $item->variant ?: ($item->product->unit ?? '') }}</p>
      <div class="mt-2 flex items-center justify-between">
        <div class="inline-flex items-center rounded-lg border border-slate-200">
          <button type="button" class="px-2.5 py-1 text-slate-500 hover:text-brand-700" data-cart-dec data-key="{{ $item->key }}" data-qty="{{ $item->qty }}">&minus;</button>
          <span class="w-7 text-center text-sm font-semibold">{{ $item->qty }}</span>
          <button type="button" class="px-2.5 py-1 text-slate-500 hover:text-brand-700" data-cart-inc data-key="{{ $item->key }}" data-qty="{{ $item->qty }}">+</button>
        </div>
        <span class="text-sm font-bold text-ink">{{ money($item->line_total) }}</span>
      </div>
    </div>
    <button type="button" class="text-slate-300 hover:text-red-500 self-start" data-cart-remove data-key="{{ $item->key }}" aria-label="Remove">
      <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18" stroke-linecap="round"/></svg>
    </button>
  </div>
@endforeach
