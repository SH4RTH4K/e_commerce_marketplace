@extends('layouts.storefront', ['headerVariant' => 'compact'])
@php $title = 'My Account'; @endphp

@section('content')
<section class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-10">
  <div class="flex items-center justify-between mb-8">
    <h1 class="font-display text-2xl sm:text-3xl font-extrabold">My Account</h1>
    <form method="POST" action="{{ route('logout') }}">@csrf<button class="text-sm font-semibold text-brand-600 hover:underline">Log out</button></form>
  </div>

  <div class="grid lg:grid-cols-[260px_1fr] gap-8">
    <aside class="space-y-2">
      <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-soft">
        <p class="font-display font-bold text-lg">{{ $user->name }}</p>
        <p class="text-sm text-slate-500">{{ $user->email }}</p>
        @if($user->phone)<p class="text-sm text-slate-500">{{ $user->phone }}</p>@endif
      </div>
      <nav class="rounded-2xl border border-slate-100 bg-white divide-y divide-slate-100 text-sm font-medium shadow-soft">
        <a href="#orders" class="block px-4 py-3 hover:bg-slate-50 rounded-t-2xl">My Orders</a>
        <a href="#profile" class="block px-4 py-3 hover:bg-slate-50">Profile details</a>
        <a href="#password" class="block px-4 py-3 hover:bg-slate-50">Change password</a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="w-full text-left px-4 py-3 hover:bg-slate-50 text-brand-600 rounded-b-2xl">Log out</button></form>
      </nav>
    </aside>

    <div class="space-y-10">
      <div id="orders">
        <h2 class="font-display text-xl font-extrabold mb-4">My Orders</h2>
        @if($orders->isEmpty())
          <div class="rounded-2xl border border-dashed border-slate-200 p-10 text-center text-slate-500">
            <p>You haven't placed any orders yet.</p>
            <a href="{{ route('shop') }}" class="mt-3 inline-flex rounded-full bg-brand-600 text-white text-sm font-semibold px-5 py-2.5 hover:bg-brand-700">Start shopping</a>
          </div>
        @else
          <div class="space-y-4">
            @foreach($orders as $order)
              <article class="rounded-2xl border border-slate-100 bg-white shadow-soft overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 bg-slate-50 border-b border-slate-100 text-sm">
                  <div>
                    <a href="{{ route('account.orders.show', $order) }}" class="font-semibold hover:text-brand-600">{{ $order->order_number }}</a>
                    <p class="text-xs text-slate-500 mt-0.5">{{ $order->created_at->format('d M Y') }}</p>
                  </div>
                  <div class="text-right">
                    <p class="font-bold text-brand-600">{{ money($order->total) }}</p>
                    <p class="text-xs text-slate-500">{{ $order->paymentMethodLabel() }} &middot; {{ ucfirst($order->payment_status) }}</p>
                  </div>
                  <span class="text-xs px-2.5 py-1 rounded-full bg-white border border-slate-200">{{ ucfirst($order->status) }}</span>
                </div>

                <ul class="divide-y divide-slate-100">
                  @foreach($order->items as $item)
                    <li class="flex items-center gap-3 px-4 py-3">
                      <img src="{{ $item->imageUrl() }}" alt="{{ $item->product_name }}" class="h-12 w-10 object-cover bg-slate-100 rounded-lg shrink-0" />
                      <div class="flex-1 min-w-0">
                        @if($item->product && $item->product->is_published)
                          <a href="{{ route('product.show', $item->product) }}" class="text-sm font-semibold truncate block hover:text-brand-600">{{ $item->product_name }}</a>
                        @else
                          <p class="text-sm font-semibold truncate">{{ $item->product_name }}</p>
                        @endif
                        <p class="text-xs text-slate-500">
                          @if($item->variant){{ $item->variant }} · @endif
                          Qty {{ $item->quantity }} &middot; {{ money($item->line_total) }}
                        </p>
                      </div>
                    </li>
                  @endforeach
                </ul>

                <div class="px-4 py-2 border-t border-slate-100 text-right">
                  <a href="{{ route('account.orders.show', $order) }}" class="text-xs font-semibold text-brand-600 hover:underline">View full details</a>
                </div>
              </article>
            @endforeach
          </div>
        @endif
      </div>

      <div id="profile">
        <h2 class="font-display text-xl font-extrabold mb-4">Profile details</h2>
        <form method="POST" action="{{ route('account.profile') }}" class="rounded-2xl border border-slate-100 bg-white p-6 grid sm:grid-cols-2 gap-4 shadow-soft">
          @csrf @method('PUT')
          <div><label class="block text-sm font-medium mb-1.5">Name</label><input name="name" value="{{ old('name', $user->name) }}" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300" /></div>
          <div><label class="block text-sm font-medium mb-1.5">Phone</label><input name="phone" value="{{ old('phone', $user->phone) }}" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300" /></div>
          <div class="sm:col-span-2"><label class="block text-sm font-medium mb-1.5">Address</label><input name="address" value="{{ old('address', $user->address) }}" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300" /></div>
          <div><label class="block text-sm font-medium mb-1.5">City</label><input name="city" value="{{ old('city', $user->city) }}" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300" /></div>
          <div><label class="block text-sm font-medium mb-1.5">Postal code</label><input name="postal_code" value="{{ old('postal_code', $user->postal_code) }}" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300" /></div>
          <div class="sm:col-span-2"><button class="rounded-full bg-brand-600 text-white font-semibold px-6 py-3 text-sm hover:bg-brand-700">Save profile</button></div>
        </form>
      </div>

      <div id="password">
        <h2 class="font-display text-xl font-extrabold mb-4">Change password</h2>
        <form method="POST" action="{{ route('account.password') }}" class="rounded-2xl border border-slate-100 bg-white p-6 grid sm:grid-cols-3 gap-4 shadow-soft">
          @csrf @method('PUT')
          <div><label class="block text-sm font-medium mb-1.5">Current</label><input type="password" name="current_password" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300" /></div>
          <div><label class="block text-sm font-medium mb-1.5">New</label><input type="password" name="password" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300" /></div>
          <div><label class="block text-sm font-medium mb-1.5">Confirm</label><input type="password" name="password_confirmation" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300" /></div>
          <div class="sm:col-span-3"><button class="rounded-full bg-brand-600 text-white font-semibold px-6 py-3 text-sm hover:bg-brand-700">Update password</button></div>
        </form>
      </div>
    </div>
  </div>
</section>
@endsection
