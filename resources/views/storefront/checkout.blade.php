@extends('layouts.storefront')

@php
  $title = 'Checkout';
  $shippingZone = old('shipping_zone', 'inside_dhaka');
  $taxable = max(0, $subtotal - $discount);
@endphp

@section('body_class', 'bg-slate-50 text-ink antialiased')

@section('checkout_header')
  <header class="bg-white border-b border-slate-100">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 h-16 lg:h-20 flex items-center justify-between">
      @include('partials.brand')
      <div class="flex items-center gap-2 text-sm text-slate-500"><svg class="h-5 w-5 text-brand-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3l8 4v5c0 5-3.5 7.5-8 9-4.5-1.5-8-4-8-9V7z"/><path stroke-linecap="round" d="m9 12 2 2 4-4"/></svg> Secure Checkout</div>
    </div>
  </header>
@endsection

@section('content')
  <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 pt-8">
    <ol class="flex items-center gap-3 text-sm font-medium">
      <li class="flex items-center gap-2 text-brand-700"><span class="grid h-7 w-7 place-items-center rounded-full bg-brand-600 text-white text-xs">1</span> <a href="{{ route('cart.index') }}" class="hover:underline">Cart</a></li>
      <li class="h-px w-8 bg-brand-300"></li>
      <li class="flex items-center gap-2 text-brand-700"><span class="grid h-7 w-7 place-items-center rounded-full bg-brand-600 text-white text-xs">2</span> Delivery</li>
      <li class="h-px w-8 bg-slate-200"></li>
      <li class="flex items-center gap-2 text-slate-400"><span class="grid h-7 w-7 place-items-center rounded-full bg-slate-200 text-slate-500 text-xs">3</span> Payment</li>
    </ol>
  </div>

  <section class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-8">
    @if($errors->any())
      <div class="mb-6 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">
        <p class="font-semibold">Please fix the following:</p>
        <ul class="list-disc list-inside mt-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    <div class="lg:grid lg:grid-cols-[1fr_380px] lg:gap-8 lg:items-start">
      <form method="POST" action="{{ route('checkout.store') }}" id="checkoutForm" class="space-y-6">
        @csrf

        <div class="rounded-2xl bg-white p-6 border border-slate-100">
          <h2 class="font-display text-lg font-extrabold">Contact</h2>
          <div class="mt-4 grid sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2"><label class="block text-sm font-medium mb-1.5">Full name</label><input name="customer_name" value="{{ old('customer_name', $user?->name) }}" required class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300" /></div>
            <div class="sm:col-span-2"><label class="block text-sm font-medium mb-1.5">Phone</label><input name="customer_phone" value="{{ old('customer_phone', $user?->phone) }}" required placeholder="01XXX-XXXXXX" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300" /></div>
            <div class="sm:col-span-2"><label class="block text-sm font-medium mb-1.5">Email (optional)</label><input type="email" name="customer_email" value="{{ old('customer_email', $user?->email) }}" placeholder="you@example.com" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300" /></div>
          </div>
        </div>

        <div class="rounded-2xl bg-white p-6 border border-slate-100">
          <h2 class="font-display text-lg font-extrabold">Delivery address</h2>
          <div class="mt-4 grid sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2"><label class="block text-sm font-medium mb-1.5">Address</label><input name="shipping_address" value="{{ old('shipping_address', $user?->address) }}" required placeholder="House, road, area" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300" /></div>
            <div><label class="block text-sm font-medium mb-1.5">City</label><input name="city" value="{{ old('city', $user?->city) }}" required class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300" /></div>
            <div><label class="block text-sm font-medium mb-1.5">Postal code</label><input name="postal_code" value="{{ old('postal_code', $user?->postal_code) }}" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300" /></div>
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium mb-1.5">Delivery zone</label>
              <select name="shipping_zone" id="shippingZone" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300">
                <option value="inside_dhaka" data-fee="{{ $shipInside }}" @selected($shippingZone === 'inside_dhaka')>{{ shipping_zone_label('inside_dhaka') }} (+{{ money($shipInside) }})</option>
                <option value="outside_dhaka" data-fee="{{ $shipOutside }}" @selected($shippingZone === 'outside_dhaka')>{{ shipping_zone_label('outside_dhaka') }} (+{{ money($shipOutside) }})</option>
              </select>
            </div>
          </div>
        </div>

        <div class="rounded-2xl bg-white p-6 border border-slate-100">
          <h2 class="font-display text-lg font-extrabold">Payment method</h2>
          @php
            $method = old('payment_method', 'cod');
            $bkash = setting('bkash_number');
            $nagad = setting('nagad_number');
            $rocket = setting('rocket_number');
            $showCod = (string) setting('pay_cod_enabled', '1') === '1';
            $showBkash = (string) setting('pay_bkash_enabled', '1') === '1' && (bool) $bkash;
            $showNagad = (string) setting('pay_nagad_enabled', '1') === '1' && (bool) $nagad;
            $showRocket = (string) setting('pay_rocket_enabled', '1') === '1' && (bool) $rocket;
            $payNumbers = [
              'bkash' => $bkash,
              'nagad' => $nagad,
              'rocket' => $rocket,
            ];
          @endphp
          <div class="mt-4 space-y-3">
            @if($showCod)
            <label class="pay-opt flex items-center gap-3 rounded-xl border-2 px-4 py-3.5 cursor-pointer {{ $method==='cod' ? 'border-brand-500 bg-brand-50/60' : 'border-slate-200 hover:border-brand-300' }}">
              <input type="radio" name="payment_method" value="cod" @checked($method==='cod') class="pay-radio text-brand-600 focus:ring-brand-500" data-manual="0" data-pay-number="" />
              <span class="font-medium">Cash on Delivery</span>
              <svg class="ml-auto h-5 w-5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/></svg>
            </label>
            @endif
            @if($showBkash)
            <label class="pay-opt flex items-center gap-3 rounded-xl border px-4 py-3.5 cursor-pointer hover:border-brand-300 {{ $method==='bkash' ? 'border-2 border-brand-500 bg-brand-50/60' : 'border-slate-200' }}">
              <input type="radio" name="payment_method" value="bkash" @checked($method==='bkash') class="pay-radio text-brand-600 focus:ring-brand-500" data-manual="1" data-pay-number="{{ $bkash }}" />
              <span class="font-medium">bKash</span>
              <span class="ml-auto rounded-md bg-pink-100 text-pink-600 text-xs font-bold px-2 py-1">{{ $bkash }}</span>
            </label>
            @endif
            @if($showNagad)
            <label class="pay-opt flex items-center gap-3 rounded-xl border px-4 py-3.5 cursor-pointer hover:border-brand-300 {{ $method==='nagad' ? 'border-2 border-brand-500 bg-brand-50/60' : 'border-slate-200' }}">
              <input type="radio" name="payment_method" value="nagad" @checked($method==='nagad') class="pay-radio text-brand-600 focus:ring-brand-500" data-manual="1" data-pay-number="{{ $nagad }}" />
              <span class="font-medium">Nagad</span>
              <span class="ml-auto rounded-md bg-orange-100 text-orange-600 text-xs font-bold px-2 py-1">{{ $nagad }}</span>
            </label>
            @endif
            @if($showRocket)
            <label class="pay-opt flex items-center gap-3 rounded-xl border px-4 py-3.5 cursor-pointer hover:border-brand-300 {{ $method==='rocket' ? 'border-2 border-brand-500 bg-brand-50/60' : 'border-slate-200' }}">
              <input type="radio" name="payment_method" value="rocket" @checked($method==='rocket') class="pay-radio text-brand-600 focus:ring-brand-500" data-manual="1" data-pay-number="{{ $rocket }}" />
              <span class="font-medium">Rocket</span>
              <span class="ml-auto rounded-md bg-purple-100 text-purple-600 text-xs font-bold px-2 py-1">{{ $rocket }}</span>
            </label>
            @endif
          </div>

          <div id="manualFields" class="mt-4 grid sm:grid-cols-2 gap-4 {{ $method==='cod' ? 'hidden' : '' }}">
            <div class="sm:col-span-2 text-xs text-slate-500">Send <b id="manualPayAmount">{{ money($totals['total']) }}</b> to <b id="manualPayNumber">{{ $payNumbers[$method] ?? "\u{2014}" }}</b>, then enter your payment details below.</div>
            <div><label class="block text-sm font-medium mb-1.5">Your sender number</label><input name="payment_sender_number" value="{{ old('payment_sender_number') }}" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300" /></div>
            <div><label class="block text-sm font-medium mb-1.5">Transaction ID</label><input name="payment_txn_id" value="{{ old('payment_txn_id') }}" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300" /></div>
          </div>
        </div>

        <button type="submit" class="btn-shine w-full rounded-full bg-brand-600 text-white font-bold py-4 hover:bg-brand-700 transition"><span id="placeLabel">Place Order</span> · <span id="placeTotal">{{ money($totals['total']) }}</span></button>
        <p class="text-center text-xs text-slate-400">By placing your order you agree to our <a href="{{ route('terms') }}" class="underline hover:text-brand-600">Terms</a> &amp; <a href="{{ route('privacy') }}" class="underline hover:text-brand-600">Privacy Policy</a>.</p>
      </form>

      <aside class="mt-8 lg:mt-0 space-y-4">
        <div class="rounded-2xl bg-white p-6 border border-slate-100">
          <h2 class="font-display text-lg font-extrabold">Coupon code</h2>
          @if($couponCode)
            <div class="mt-3 flex items-center justify-between gap-3 bg-brand-50 border border-brand-200 rounded-xl px-4 py-3">
              <div>
                <p class="font-mono font-bold text-brand-700">{{ $couponCode }}</p>
                <p class="text-xs text-brand-600">You save {{ money($discount) }}</p>
              </div>
              <form method="POST" action="{{ route('checkout.coupon.remove') }}">
                @csrf
                <button type="submit" class="text-xs font-semibold text-slate-500 hover:text-red-600">Remove</button>
              </form>
            </div>
          @else
            <form method="POST" action="{{ route('checkout.coupon.apply') }}" class="mt-3 flex gap-2">
              @csrf
              <input name="code" value="{{ old('code') }}" placeholder="Promo code" required class="flex-1 rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300" />
              <button type="submit" class="rounded-xl bg-brand-600 text-white text-sm font-semibold px-4 hover:bg-brand-700 transition">Apply</button>
            </form>
            @error('coupon')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
          @endif
        </div>

        <div class="rounded-2xl bg-white p-6 border border-slate-100 lg:sticky lg:top-8">
          <h2 class="font-display text-lg font-extrabold">Your order</h2>
          <div class="mt-5 space-y-4">
            @foreach($items as $item)
              <div class="flex gap-3">
                <div class="relative"><img src="{{ image_url($item->image, $item->name) }}" class="h-14 w-14 rounded-xl object-cover" alt="{{ $item->name }}" /><span class="absolute -top-2 -right-2 grid h-5 w-5 place-items-center rounded-full bg-brand-600 text-white text-[10px] font-bold">{{ $item->qty }}</span></div>
                <div class="flex-1 min-w-0"><p class="text-sm font-semibold truncate">{{ $item->name }}</p><p class="text-xs text-slate-500">{{ $item->variant ?: $item->product->unit }}</p></div>
                <span class="text-sm font-bold">{{ money($item->line_total) }}</span>
              </div>
            @endforeach
          </div>
          <dl class="mt-5 space-y-2.5 text-sm border-t border-slate-100 pt-5">
            <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd class="font-semibold">{{ money($subtotal) }}</dd></div>
            @if($discount > 0)
              <div class="flex justify-between text-brand-600"><dt>Discount ({{ $couponCode }})</dt><dd class="font-semibold" id="sumDiscount">−{{ money($discount) }}</dd></div>
            @endif
            <div class="flex justify-between" id="rowDelivery"><dt class="text-slate-500">Delivery</dt><dd class="font-semibold text-brand-600" id="sumShipping">{{ money($totals['shipping']) }}</dd></div>
            <div class="flex justify-between" id="rowTax"><dt class="text-slate-500">Tax ({{ rtrim(rtrim(number_format($taxPercent, 2), '0'), '.') }}%)</dt><dd class="font-semibold" id="sumTax">{{ money($totals['tax']) }}</dd></div>
          </dl>

          {{-- Standard total (mobile banking / non-COD) --}}
          <div id="totalStandard" class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-between">
            <span class="font-semibold">Total</span>
            <span class="font-display text-2xl font-extrabold text-brand-700" id="sumTotal">{{ money($totals['total']) }}</span>
          </div>

          {{-- COD split: pay delivery now, rest after delivery --}}
          <div id="totalCod" class="mt-4 pt-4 border-t border-slate-100 space-y-3 hidden">
            <div class="flex items-center justify-between">
              <span class="font-semibold text-brand-800">Pay now <span class="text-xs font-normal text-slate-500">(delivery)</span></span>
              <span class="font-display text-2xl font-extrabold text-brand-700" id="sumPayNow">{{ money($totals['shipping']) }}</span>
            </div>
            <div class="flex items-center justify-between rounded-xl bg-amber-50 border border-amber-100 px-3 py-2.5">
              <span class="text-sm font-medium text-amber-900">Pay after delivery</span>
              <span class="text-sm font-bold text-amber-900" id="sumPayLater">{{ money(max(0, $totals['total'] - $totals['shipping'])) }}</span>
            </div>
            <p class="text-xs text-slate-500">Order total {{ money($totals['total']) }} — only the delivery charge is collected upfront for Cash on Delivery.</p>
          </div>

          @if(trim((string) setting('delivery_eta_text', '')) !== '')
            <div class="mt-5 flex items-center gap-2.5 rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-800"><svg class="h-5 w-5 shrink-0 text-brand-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 7h11v8H3zM14 10h4l3 3v2h-7"/><circle cx="7" cy="18" r="1.5"/><circle cx="17" cy="18" r="1.5"/></svg> {{ setting('delivery_eta_text') }}</div>
          @endif
        </div>
      </aside>
    </div>
  </section>
@endsection

@push('scripts')
<script>
(function () {
  var subtotal = {{ $subtotal }};
  var discount = {{ $discount }};
  var taxPct = {{ $taxPercent }};
  var zone = document.getElementById('shippingZone');
  var currencySymbol = @json(currency_symbol());
  var money = function (n) {
    return currencySymbol + ' ' + Number(n).toLocaleString('en-US', { maximumFractionDigits: 0 });
  };

  function selectedPayMethod() {
    var el = document.querySelector('input[name="payment_method"]:checked');
    return el ? el.value : 'cod';
  }

  function isCod() {
    return selectedPayMethod() === 'cod';
  }

  function recalc() {
    var fee = parseFloat(zone.options[zone.selectedIndex].dataset.fee) || 0;
    var taxable = Math.max(0, subtotal - discount);
    var tax = Math.round(taxable * taxPct / 100);
    var total = taxable + fee + tax;
    var payLater = Math.max(0, total - fee);
    var cod = isCod();

    document.getElementById('sumShipping').textContent = money(fee);
    document.getElementById('sumTax').textContent = money(tax);
    document.getElementById('sumTotal').textContent = money(total);

    var payNowEl = document.getElementById('sumPayNow');
    var payLaterEl = document.getElementById('sumPayLater');
    if (payNowEl) payNowEl.textContent = money(fee);
    if (payLaterEl) payLaterEl.textContent = money(payLater);

    var std = document.getElementById('totalStandard');
    var codBox = document.getElementById('totalCod');
    if (std) std.classList.toggle('hidden', cod);
    if (codBox) codBox.classList.toggle('hidden', !cod);

    // Place-order button: delivery only for COD, full total otherwise
    var placeLabel = document.getElementById('placeLabel');
    if (placeLabel) placeLabel.textContent = cod ? 'Place Order · Pay now' : 'Place Order';
    document.getElementById('placeTotal').textContent = money(cod ? fee : total);

    var manualAmt = document.getElementById('manualPayAmount');
    if (manualAmt) manualAmt.textContent = money(total);
  }

  if (zone) zone.addEventListener('change', recalc);

  document.querySelectorAll('.pay-radio').forEach(function (r) {
    r.addEventListener('change', function () {
      document.querySelectorAll('.pay-opt').forEach(function (o) {
        o.classList.remove('border-2', 'border-brand-500', 'bg-brand-50/60');
        o.classList.add('border-slate-200');
      });
      var label = r.closest('.pay-opt');
      label.classList.add('border-2', 'border-brand-500', 'bg-brand-50/60');
      label.classList.remove('border-slate-200');
      document.getElementById('manualFields').classList.toggle('hidden', r.dataset.manual === '0');
      var numEl = document.getElementById('manualPayNumber');
      if (numEl) numEl.textContent = r.dataset.payNumber || "\u{2014}";
      recalc();
    });
  });

  recalc();
})();
</script>
@endpush
