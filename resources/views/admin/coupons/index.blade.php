@extends('layouts.admin')
@section('title', 'Coupons')

@section('content')
<div class="space-y-6">
  <div class="flex items-center justify-between flex-wrap gap-3">
    <h2 class="text-xl font-bold">Coupons</h2>
    <a href="{{ route('admin.coupons.create') }}" class="btn-primary">+ New coupon</a>
  </div>

  <form method="GET" class="flex gap-2">
    <input name="q" value="{{ $q }}" placeholder="Search code or description…" class="inp max-w-xs" />
    <button class="btn-primary">Search</button>
    @if($q)<a href="{{ route('admin.coupons.index') }}" class="px-3 py-2 text-sm text-gray-500 hover:text-primary">Clear</a>@endif
  </form>

  <div class="card overflow-hidden">
    <form id="couponsBulkForm" method="POST" action="{{ route('admin.coupons.bulk') }}" data-bulk-form>
      @csrf
      <div data-bulk-bar class="px-4 py-3 border-b border-gray-200 bg-primary/5 flex flex-wrap items-center gap-3">
        <span class="text-sm font-medium text-ink"><span data-bulk-count>0</span> selected</span>
        <select name="bulk_action" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm bg-white">
          <option value="">Choose action…</option>
          <option value="activate">Set Active</option>
          <option value="deactivate">Set Inactive</option>
          <option value="delete" data-confirm="Delete :count selected coupon(s)?">Delete</option>
        </select>
        <button type="submit" data-bulk-apply class="btn-primary text-sm py-1.5" disabled>Apply</button>
      </div>
    </form>

    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="text-left text-gray-500 bg-gray-50">
          <tr>
            <th class="px-5 py-3 font-medium w-10">
              <input type="checkbox" form="couponsBulkForm" data-bulk-select-all class="accent-primary h-4 w-4" title="Select all" aria-label="Select all" />
            </th>
            <th class="px-5 py-3 font-medium">Code</th>
            <th class="px-5 py-3 font-medium">Discount</th>
            <th class="px-5 py-3 font-medium">Min order</th>
            <th class="px-5 py-3 font-medium">Uses</th>
            <th class="px-5 py-3 font-medium">Valid until</th>
            <th class="px-5 py-3 font-medium">Status</th>
            <th class="px-5 py-3 font-medium text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @forelse($coupons as $coupon)
            <tr class="hover:bg-gray-50">
              <td class="px-5 py-3">
                <input type="checkbox" form="couponsBulkForm" name="ids[]" value="{{ $coupon->id }}" data-bulk-row class="accent-primary h-4 w-4" aria-label="Select {{ $coupon->code }}" />
              </td>
              <td class="px-5 py-3">
                <p class="font-mono font-semibold">{{ $coupon->code }}</p>
                @if($coupon->description)<p class="text-xs text-gray-400">{{ $coupon->description }}</p>@endif
              </td>
              <td class="px-5 py-3">{{ $coupon->valueLabel() }}</td>
              <td class="px-5 py-3 text-gray-500">{{ $coupon->min_order_amount ? money($coupon->min_order_amount) : "\u{2014}" }}</td>
              <td class="px-5 py-3">{{ $coupon->used_count }}{{ $coupon->max_uses ? ' / '.$coupon->max_uses : '' }}</td>
              <td class="px-5 py-3 text-gray-500">{{ $coupon->expires_at?->format('d M Y') ?? "\u{2014}" }}</td>
              <td class="px-5 py-3">
                @if($coupon->isCurrentlyActive())
                  <span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-700">Active</span>
                @else
                  <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-500">Inactive</span>
                @endif
              </td>
              <td class="px-5 py-3 text-right whitespace-nowrap">
                <a href="{{ route('admin.coupons.edit', $coupon) }}" class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-600">Edit</a>
                <form method="POST" action="{{ route('admin.coupons.destroy', $coupon) }}" class="inline" onsubmit="return confirm('Delete this coupon?')">@csrf @method('DELETE')<button class="px-2 py-1 text-xs rounded bg-red-50 text-red-600 border border-red-200">Delete</button></form>
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="px-5 py-10 text-center text-gray-400">No coupons yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $coupons->links() }}
</div>
@endsection
