@if ($product->allow_backorder)
  <aside role="note" aria-label="Product availability" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
    <p><strong>{{ $product->availableStockQuantity() > 0 ? 'In Stock' : 'Available to order' }}</strong></p>
    <p class="mt-1">We source unavailable items after receiving your order. We will confirm availability and delivery timing before dispatch.</p>
  </aside>
@endif
