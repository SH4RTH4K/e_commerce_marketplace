@extends('layouts.admin')
@php $editing = $product->exists; @endphp
@section('title', $editing ? 'Edit Product' : 'New Product')

@section('content')
@php
  $mapRows = function ($type) use ($editing, $product) {
      return old(
          strtolower($type) === 'size' ? 'size_variants' : (strtolower($type) === 'color' ? 'color_variants' : 'weight_variants'),
          $editing
              ? $product->variants->where('type', $type)->values()->map(fn ($v) => [
                  'value' => $v->value,
                  'price_delta' => (float) $v->price_delta,
                  'stock' => (int) $v->stock,
                ])->all()
              : [['value' => '', 'price_delta' => 0, 'stock' => 0]]
      );
  };
  $sizeRows = $mapRows('Size');
  $colorRows = $mapRows('Color');
  $weightRows = $mapRows('Weight');
  if ($sizeRows === []) { $sizeRows = [['value' => '', 'price_delta' => 0, 'stock' => 0]]; }
  if ($colorRows === []) { $colorRows = [['value' => '', 'price_delta' => 0, 'stock' => 0]]; }
  if ($weightRows === []) { $weightRows = [['value' => '', 'price_delta' => 0, 'stock' => 0]]; }
@endphp
<form method="POST" action="{{ $editing ? route('admin.products.update', $product) : route('admin.products.store') }}" enctype="multipart/form-data">
  @csrf
  @if($editing) @method('PUT') @endif

  <div class="flex items-center justify-between mb-6">
    <div>
      <a href="{{ route('admin.products.index') }}" class="text-sm text-gray-500 hover:text-primary">&larr; Back to products</a>
      <h2 class="text-xl font-bold mt-1">{{ $editing ? 'Edit Product' : 'New Product' }}</h2>
    </div>
    <div class="flex gap-2">
      <a href="{{ route('admin.products.index') }}" class="px-4 py-2 text-sm rounded-lg border border-gray-300 bg-white">Cancel</a>
      <button class="btn-primary">Save</button>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
      <!-- Basic -->
      <div class="card p-5 space-y-4">
        <h3 class="font-semibold">Basic information</h3>
        <div><label class="lbl">Name</label><input name="name" class="inp" value="{{ old('name', $product->name) }}" required /></div>
        <div class="grid grid-cols-2 gap-4">
          <div><label class="lbl">Slug (blank = auto)</label><input name="slug" class="inp" value="{{ old('slug', $product->slug) }}" /></div>
          <div><label class="lbl">SKU</label><input name="sku" class="inp" value="{{ old('sku', $product->sku) }}" /></div>
        </div>
        <div><label class="lbl">Brand</label><input name="brand" class="inp" value="{{ old('brand', $product->brand) }}" /></div>
        <div><label class="lbl">Short description</label><textarea name="short_description" class="inp" rows="2">{{ old('short_description', $product->short_description) }}</textarea></div>
        <div><label class="lbl">Full description</label><textarea name="description" class="inp" rows="6">{{ old('description', $product->description) }}</textarea></div>
      </div>

      <!-- Specifications -->
      @php
        $oldLabels = old('spec_labels');
        $oldValues = old('spec_values');
        if (is_array($oldLabels)) {
          $specRows = [];
          foreach ($oldLabels as $i => $label) {
            $specRows[] = ['label' => $label, 'value' => $oldValues[$i] ?? ''];
          }
        } else {
          $specRows = $product->specificationRows();
        }
        if (empty($specRows)) {
          $specRows = [['label' => '', 'value' => '']];
        }
      @endphp
      <div class="card p-5 space-y-4">
        <div class="flex items-center justify-between gap-3">
          <div>
            <h3 class="font-semibold">Specifications</h3>
            <p class="text-sm text-gray-400 mt-0.5">Shown as bullets on product cards and in the product page table.</p>
          </div>
          <button type="button" id="addSpecRow" class="px-3 py-1.5 text-sm rounded-lg border border-gray-300 bg-white hover:bg-gray-50">+ Add row</button>
        </div>
        <div id="specRows" class="space-y-3">
          @foreach($specRows as $row)
            <div class="spec-row grid grid-cols-[1fr_1fr_auto] gap-2 items-start">
              <div>
                <label class="lbl">Label</label>
                <input name="spec_labels[]" class="inp" value="{{ $row['label'] ?? '' }}" placeholder="e.g. Model" />
              </div>
              <div>
                <label class="lbl">Value</label>
                <input name="spec_values[]" class="inp" value="{{ $row['value'] ?? '' }}" placeholder="e.g. Ryzen 5 2400G" />
              </div>
              <button type="button" class="remove-spec-row mt-6 h-10 w-10 rounded-lg border border-gray-200 text-gray-400 hover:text-red-600 hover:border-red-200" title="Remove" aria-label="Remove row">×</button>
            </div>
          @endforeach
        </div>
      </div>

      <!-- Pricing -->
      <div class="card p-5 space-y-4">
        <h3 class="font-semibold">Pricing & stock</h3>
        <div class="grid grid-cols-2 gap-4">
          <div><label class="lbl">Regular price (৳)</label><input name="regular_price" type="number" step="0.01" class="inp" value="{{ old('regular_price', $product->regular_price) }}" required /></div>
          <div><label class="lbl">Sale price (৳)</label><input name="sale_price" type="number" step="0.01" class="inp" value="{{ old('sale_price', $product->sale_price) }}" placeholder="optional" /></div>
          <div><label class="lbl">Stock quantity</label><input name="stock_quantity" type="number" class="inp" value="{{ old('stock_quantity', $product->stock_quantity ?? 0) }}" required /></div>
          <div><label class="lbl">Unit / default size</label><input name="unit" class="inp" value="{{ old('unit', $product->unit) }}" placeholder="e.g. M" /></div>
          <div><label class="lbl">Rating (from reviews)</label><input type="text" class="inp bg-gray-50" value="{{ number_format((float) ($product->rating ?? 0), 2) }} ★ · {{ (int) ($product->reviews_count ?? 0) }} reviews" readonly /></div>
        </div>
              <p class="text-xs text-gray-400">Base selling price. Option price adjustments below are added on top.</p>
      </div>

      <!-- Variants -->
      <div class="card p-5 space-y-5">
        <div>
          <h3 class="font-semibold">Variants</h3>
          <p class="text-sm text-gray-400 mt-1">Each option can have a price adjustment (৳). Final price = base + selected deltas. Leave a row blank to skip it.</p>
        </div>

        <div>
          <div class="flex items-center justify-between mb-2">
            <h4 class="text-sm font-semibold text-gray-700">Sizes</h4>
            <button type="button" class="text-xs font-semibold text-primary" data-variant-add="sizeRows">+ Add size</button>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="text-left text-xs text-gray-400 border-b">
                  <th class="py-2 pr-2 font-medium">Value</th>
                  <th class="py-2 pr-2 font-medium w-36">Price adj. (৳)</th>
                  <th class="py-2 pr-2 font-medium w-28">Stock</th>
                  <th class="py-2 w-10"></th>
                </tr>
              </thead>
              <tbody id="sizeRows">
                @foreach($sizeRows as $i => $row)
                  <tr class="border-b border-gray-50">
                    <td class="py-2 pr-2"><input name="size_variants[{{ $i }}][value]" class="inp" value="{{ $row['value'] ?? '' }}" placeholder="e.g. XL" /></td>
                    <td class="py-2 pr-2"><input name="size_variants[{{ $i }}][price_delta]" type="number" step="0.01" class="inp" value="{{ $row['price_delta'] ?? 0 }}" placeholder="0" /></td>
                    <td class="py-2 pr-2"><input name="size_variants[{{ $i }}][stock]" type="number" min="0" class="inp" value="{{ $row['stock'] ?? 0 }}" /></td>
                    <td class="py-2"><button type="button" class="text-red-500 text-lg leading-none px-1" data-variant-remove title="Remove">&times;</button></td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>

        <div>
          <div class="flex items-center justify-between mb-2">
            <h4 class="text-sm font-semibold text-gray-700">Colors</h4>
            <button type="button" class="text-xs font-semibold text-primary" data-variant-add="colorRows">+ Add color</button>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="text-left text-xs text-gray-400 border-b">
                  <th class="py-2 pr-2 font-medium">Value</th>
                  <th class="py-2 pr-2 font-medium w-36">Price adj. (৳)</th>
                  <th class="py-2 pr-2 font-medium w-28">Stock</th>
                  <th class="py-2 w-10"></th>
                </tr>
              </thead>
              <tbody id="colorRows">
                @foreach($colorRows as $i => $row)
                  <tr class="border-b border-gray-50">
                    <td class="py-2 pr-2"><input name="color_variants[{{ $i }}][value]" class="inp" value="{{ $row['value'] ?? '' }}" placeholder="e.g. Black" /></td>
                    <td class="py-2 pr-2"><input name="color_variants[{{ $i }}][price_delta]" type="number" step="0.01" class="inp" value="{{ $row['price_delta'] ?? 0 }}" placeholder="0" /></td>
                    <td class="py-2 pr-2"><input name="color_variants[{{ $i }}][stock]" type="number" min="0" class="inp" value="{{ $row['stock'] ?? 0 }}" /></td>
                    <td class="py-2"><button type="button" class="text-red-500 text-lg leading-none px-1" data-variant-remove title="Remove">&times;</button></td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>

        <div>
          <div class="flex items-center justify-between mb-2">
            <h4 class="text-sm font-semibold text-gray-700">Weights / packs</h4>
            <button type="button" class="text-xs font-semibold text-primary" data-variant-add="weightRows">+ Add weight</button>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="text-left text-xs text-gray-400 border-b">
                  <th class="py-2 pr-2 font-medium">Value</th>
                  <th class="py-2 pr-2 font-medium w-36">Price adj. (৳)</th>
                  <th class="py-2 pr-2 font-medium w-28">Stock</th>
                  <th class="py-2 w-10"></th>
                </tr>
              </thead>
              <tbody id="weightRows">
                @foreach($weightRows as $i => $row)
                  <tr class="border-b border-gray-50">
                    <td class="py-2 pr-2"><input name="weight_variants[{{ $i }}][value]" class="inp" value="{{ $row['value'] ?? '' }}" placeholder="e.g. 1kg" /></td>
                    <td class="py-2 pr-2"><input name="weight_variants[{{ $i }}][price_delta]" type="number" step="0.01" class="inp" value="{{ $row['price_delta'] ?? 0 }}" placeholder="0" /></td>
                    <td class="py-2 pr-2"><input name="weight_variants[{{ $i }}][stock]" type="number" min="0" class="inp" value="{{ $row['stock'] ?? 0 }}" /></td>
                    <td class="py-2"><button type="button" class="text-red-500 text-lg leading-none px-1" data-variant-remove title="Remove">&times;</button></td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>

<!-- Images -->
      <div class="card p-5 space-y-4">
        <h3 class="font-semibold">Images</h3>
        @if($editing && $product->images->isNotEmpty())
          <div class="flex gap-3 flex-wrap">
            @foreach($product->images as $img)
              <div class="relative group">
                <img src="{{ $img->url() }}" class="h-24 w-24 object-cover rounded-lg bg-gray-100 {{ $img->is_primary ? 'ring-2 ring-primary' : '' }}" alt="">
                @if($img->is_primary)<span class="absolute top-1 left-1 bg-primary text-white text-[10px] px-1.5 rounded">Main</span>@endif
                <button type="submit" form="delimg{{ $img->id }}" class="absolute top-1 right-1 bg-red-600 text-white h-5 w-5 rounded-full text-xs leading-none opacity-0 group-hover:opacity-100" title="Remove">×</button>
              </div>
            @endforeach
          </div>
        @endif
        <div>
          <label class="lbl">Add images (first upload becomes the main image if none set)</label>
          <input name="images[]" type="file" accept="image/*" multiple class="text-sm" />
        </div>
      </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">
      <div class="card p-5 space-y-3">
        <h3 class="font-semibold">Organization</h3>
        <div>
          <label class="lbl">Category</label>
          <select name="category_id" class="inp" required>
            @foreach($categories as $cat)
              <option value="{{ $cat->id }}" @selected((int) old('category_id', $product->category_id) === $cat->id)>{{ $cat->name }}</option>
            @endforeach
          </select>
        </div>
      </div>

      <div class="card p-5 space-y-3">
        <h3 class="font-semibold">Visibility</h3>
        <label class="flex items-center justify-between text-sm cursor-pointer gap-3">
          <span>
            <span class="block font-medium">Active</span>
            <span class="block text-xs text-gray-400 mt-0.5">Inactive products are hidden from the storefront (use this for out-of-stock items).</span>
          </span>
          <input type="checkbox" name="is_published" value="1" class="h-5 w-9 accent-primary shrink-0" @checked(old('is_published', $product->is_published)) />
        </label>

        @php
          $toggles = [
            'is_featured' => 'Featured',
            'is_new_arrival' => 'New arrival',
            'is_best_seller' => 'Best seller',
            'is_flash_sale'  => 'Flash sale',
          ];
        @endphp
        @foreach($toggles as $field => $label)
          <label class="flex items-center justify-between text-sm cursor-pointer">
            <span>{{ $label }}</span>
            <input type="checkbox" name="{{ $field }}" value="1" class="h-5 w-9 accent-primary" @checked(old($field, $product->$field)) />
          </label>
        @endforeach
      </div>

      <div class="card p-5 space-y-3">
        <h3 class="font-semibold">SEO</h3>
        <div><label class="lbl">Meta title</label><input name="meta_title" class="inp" value="{{ old('meta_title', $product->meta_title) }}" /></div>
        <div><label class="lbl">Meta description</label><textarea name="meta_description" class="inp" rows="2">{{ old('meta_description', $product->meta_description) }}</textarea></div>
        <div><label class="lbl">Meta keywords (comma-separated)</label><textarea name="meta_keywords" class="inp" rows="2" placeholder="jacket, leather, men, buy online">{{ old('meta_keywords', $product->meta_keywords) }}</textarea></div>
      </div>
    </div>
  </div>
</form>

@if($editing)
  @foreach($product->images as $img)
    <form id="delimg{{ $img->id }}" method="POST" action="{{ route('admin.products.images.destroy', [$product, $img]) }}" onsubmit="return confirm('Remove this image?')">@csrf @method('DELETE')</form>
  @endforeach
@endif

@push('scripts')
<script>
(function () {
  const list = document.getElementById('specRows');
  const addBtn = document.getElementById('addSpecRow');
  if (!list || !addBtn) return;

  function bindRemove(btn) {
    btn.addEventListener('click', () => {
      const rows = list.querySelectorAll('.spec-row');
      if (rows.length <= 1) {
        rows[0].querySelectorAll('input').forEach((i) => { i.value = ''; });
        return;
      }
      btn.closest('.spec-row')?.remove();
    });
  }

  list.querySelectorAll('.remove-spec-row').forEach(bindRemove);

  addBtn.addEventListener('click', () => {
    const row = document.createElement('div');
    row.className = 'spec-row grid grid-cols-[1fr_1fr_auto] gap-2 items-start';
    row.innerHTML = `
      <div>
        <label class="lbl">Label</label>
        <input name="spec_labels[]" class="inp" value="" placeholder="e.g. Model" />
      </div>
      <div>
        <label class="lbl">Value</label>
        <input name="spec_values[]" class="inp" value="" placeholder="e.g. Ryzen 5 2400G" />
      </div>
      <button type="button" class="remove-spec-row mt-6 h-10 w-10 rounded-lg border border-gray-200 text-gray-400 hover:text-red-600 hover:border-red-200" title="Remove" aria-label="Remove row">×</button>
    `;
    list.appendChild(row);
    bindRemove(row.querySelector('.remove-spec-row'));
  });
})();
</script>
@endpush

<template id="variantRowTpl">
  <tr class="border-b border-gray-50">
    <td class="py-2 pr-2"><input class="inp" data-field="value" placeholder="Value" /></td>
    <td class="py-2 pr-2"><input type="number" step="0.01" class="inp" data-field="price_delta" value="0" /></td>
    <td class="py-2 pr-2"><input type="number" min="0" class="inp" data-field="stock" value="0" /></td>
    <td class="py-2"><button type="button" class="text-red-500 text-lg leading-none px-1" data-variant-remove title="Remove">&times;</button></td>
  </tr>
</template>
<script>
(function () {
  const tpl = document.getElementById('variantRowTpl');
  const prefixMap = { sizeRows: 'size_variants', colorRows: 'color_variants', weightRows: 'weight_variants' };
  function reindex(tbody, prefix) {
    Array.from(tbody.querySelectorAll('tr')).forEach((tr, i) => {
      tr.querySelectorAll('input').forEach((input) => {
        const field = input.dataset.field || (input.name.match(/\[(\w+)]$/) || [])[1];
        if (field) input.name = prefix + '[' + i + '][' + field + ']';
      });
    });
  }
  document.querySelectorAll('[data-variant-add]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const id = btn.getAttribute('data-variant-add');
      const tbody = document.getElementById(id);
      const prefix = prefixMap[id];
      if (!tbody || !tpl || !prefix) return;
      tbody.appendChild(tpl.content.cloneNode(true));
      reindex(tbody, prefix);
    });
  });
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-variant-remove]');
    if (!btn) return;
    const tr = btn.closest('tr');
    const tbody = tr && tr.parentElement;
    if (!tr || !tbody) return;
    if (tbody.querySelectorAll('tr').length <= 1) {
      tr.querySelectorAll('input').forEach((input) => { input.value = input.type === 'number' ? '0' : ''; });
      return;
    }
    tr.remove();
    const prefix = prefixMap[tbody.id];
    if (prefix) reindex(tbody, prefix);
  });
})();
</script>
@endsection
