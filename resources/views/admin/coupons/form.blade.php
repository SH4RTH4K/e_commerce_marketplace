@extends('layouts.admin')
@php $editing = $coupon->exists; @endphp
@section('title', $editing ? 'Edit Coupon' : 'New Coupon')

@section('content')
<form method="POST" action="{{ $editing ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}">
  @csrf
  @if($editing) @method('PUT') @endif

  <div class="flex items-center justify-between mb-6">
    <div>
      <a href="{{ route('admin.coupons.index') }}" class="text-sm text-gray-500 hover:text-primary">&larr; Back</a>
      <h2 class="text-xl font-bold mt-1">{{ $editing ? 'Edit Coupon' : 'New Coupon' }}</h2>
    </div>
    <button class="btn-primary">Save</button>
  </div>

  <div class="max-w-2xl card p-5 space-y-4">
    <div class="grid sm:grid-cols-2 gap-4">
      <div>
        <label class="lbl">Code</label>
        <input name="code" class="inp font-mono uppercase" value="{{ old('code', $coupon->code) }}" required placeholder="SAVE10" />
        <p class="text-xs text-gray-400 mt-1">Stored uppercase. Customers enter this at checkout.</p>
      </div>
      <div>
        <label class="lbl">Description (internal)</label>
        <input name="description" class="inp" value="{{ old('description', $coupon->description) }}" placeholder="Summer sale 10% off" />
      </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
      <div>
        <label class="lbl">Discount type</label>
        <select name="type" class="inp" id="couponType">
          <option value="percentage" @selected(old('type', $coupon->type)==='percentage')>Percentage (%)</option>
          <option value="fixed" @selected(old('type', $coupon->type)==='fixed')>Fixed amount (৳)</option>
        </select>
      </div>
      <div>
        <label class="lbl" id="valueLabel">{{ old('type', $coupon->type)==='fixed' ? 'Amount (৳)' : 'Percentage (%)' }}</label>
        <input name="value" type="number" step="0.01" min="0.01" class="inp" value="{{ old('value', $coupon->value) }}" required />
      </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
      <div>
        <label class="lbl">Minimum order subtotal (৳)</label>
        <input name="min_order_amount" type="number" step="0.01" min="0" class="inp" value="{{ old('min_order_amount', $coupon->min_order_amount) }}" placeholder="Optional" />
      </div>
      <div id="maxDiscountWrap">
        <label class="lbl">Max discount cap (৳)</label>
        <input name="max_discount" type="number" step="0.01" min="0" class="inp" value="{{ old('max_discount', $coupon->max_discount) }}" placeholder="For % coupons only" />
      </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
      <div>
        <label class="lbl">Max total uses</label>
        <input name="max_uses" type="number" min="1" class="inp" value="{{ old('max_uses', $coupon->max_uses) }}" placeholder="Leave blank = unlimited" />
        @if($editing)<p class="text-xs text-gray-400 mt-1">Used {{ $coupon->used_count }} time(s) so far.</p>@endif
      </div>
      <div></div>
      <div>
        <label class="lbl">Starts at</label>
        <input name="starts_at" type="datetime-local" class="inp" value="{{ old('starts_at', $coupon->starts_at?->format('Y-m-d\TH:i')) }}" />
      </div>
      <div>
        <label class="lbl">Expires at</label>
        <input name="expires_at" type="datetime-local" class="inp" value="{{ old('expires_at', $coupon->expires_at?->format('Y-m-d\TH:i')) }}" />
      </div>
    </div>

    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" class="accent-primary" @checked(old('is_active', $coupon->is_active ?? true)) /> Active</label>
  </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
  var type = document.getElementById('couponType');
  var valueLabel = document.getElementById('valueLabel');
  var maxWrap = document.getElementById('maxDiscountWrap');
  function sync() {
    var isFixed = type.value === 'fixed';
    valueLabel.textContent = isFixed ? 'Amount (৳)' : 'Percentage (%)';
    maxWrap.style.display = isFixed ? 'none' : '';
  }
  type.addEventListener('change', sync);
  sync();
})();
</script>
@endpush
