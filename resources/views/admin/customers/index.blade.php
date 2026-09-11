@extends('layouts.admin')
@section('title', 'Customers')

@section('content')
<div class="space-y-6">
  <div class="flex items-center justify-between">
    <h2 class="text-xl font-bold">Customers</h2>
  </div>

  <div class="card">
    <form method="GET" class="p-4 border-b border-gray-200">
      <input type="text" name="q" value="{{ $term }}" placeholder="Search name or phone…" class="w-full max-w-sm border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40" />
    </form>

    <form id="customersBulkForm" data-bulk-form onsubmit="return false;">
      <div data-bulk-bar class="px-4 py-3 border-b border-gray-200 bg-primary/5 flex flex-wrap items-center gap-3">
        <span class="text-sm font-medium text-ink"><span data-bulk-count>0</span> selected</span>
        <span class="text-xs text-gray-500">Customer rows are summaries from orders — use View for details. No bulk delete.</span>
      </div>
    </form>

    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="text-left text-gray-500 bg-gray-50">
          <tr>
            <th class="px-5 py-3 font-medium w-10">
              <input type="checkbox" form="customersBulkForm" data-bulk-select-all class="accent-primary h-4 w-4" title="Select all" aria-label="Select all" />
            </th>
            <th class="px-5 py-3 font-medium">Customer</th>
            <th class="px-5 py-3 font-medium">Phone</th>
            <th class="px-5 py-3 font-medium">Orders</th>
            <th class="px-5 py-3 font-medium">Total spent</th>
            <th class="px-5 py-3 font-medium">Last order</th>
            <th class="px-5 py-3 font-medium text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @forelse($customers as $c)
            <tr class="hover:bg-gray-50">
              <td class="px-5 py-3">
                <input type="checkbox" form="customersBulkForm" data-bulk-row class="accent-primary h-4 w-4" aria-label="Select {{ $c->customer_name }}" />
              </td>
              <td class="px-5 py-3 font-medium">{{ $c->customer_name }}</td>
              <td class="px-5 py-3 text-gray-500">{{ $c->customer_phone }}</td>
              <td class="px-5 py-3">{{ $c->orders_count }}</td>
              <td class="px-5 py-3 font-medium">{{ money($c->total_spent) }}</td>
              <td class="px-5 py-3 text-gray-500">{{ \Illuminate\Support\Carbon::parse($c->last_order_at)->format('d M Y') }}</td>
              <td class="px-5 py-3 text-right"><a href="{{ route('admin.customers.show', $c->customer_phone) }}" class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-600">View</a></td>
            </tr>
          @empty
            <tr><td colspan="7" class="px-5 py-10 text-center text-gray-400">No customers yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="p-4 border-t border-gray-200">{{ $customers->links() }}</div>
  </div>
</div>
@endsection
