@extends('layouts.admin')
@section('title', 'Reviews')

@section('content')
<div class="space-y-6">
  <div>
    <h2 class="text-xl font-bold">Product reviews</h2>
    <p class="text-sm text-gray-500 mt-1">Approve or reject customer reviews. Approved reviews update product star ratings.</p>
  </div>

  <div class="flex flex-wrap gap-2">
    @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label)
      <a href="{{ route('admin.reviews.index', array_filter(['status' => $key, 'q' => $q])) }}"
         class="px-3 py-1.5 rounded-full text-sm font-semibold {{ $status === $key ? 'bg-brand-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-brand-50' }}">
        {{ $label }}
        <span class="ml-1 opacity-80">{{ $counts[$key] ?? 0 }}</span>
      </a>
    @endforeach
  </div>

  <form method="GET" class="flex flex-wrap gap-2">
    <input type="hidden" name="status" value="{{ $status }}" />
    <input name="q" value="{{ $q }}" placeholder="Search author, product, text…" class="inp max-w-sm" />
    <button class="btn-primary">Search</button>
  </form>

  <div class="card overflow-hidden">
    <form id="reviewsBulkForm" method="POST" action="{{ route('admin.reviews.bulk') }}" data-bulk-form>
      @csrf
      <div data-bulk-bar class="px-4 py-3 border-b border-gray-200 bg-primary/5 flex flex-wrap items-center gap-3">
        <span class="text-sm font-medium text-ink"><span data-bulk-count>0</span> selected</span>
        <select name="bulk_action" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm bg-white">
          <option value="">Choose action…</option>
          <option value="approve">Approve</option>
          <option value="reject">Reject</option>
          <option value="delete" data-confirm="Delete :count selected review(s) permanently?">Delete</option>
        </select>
        <button type="submit" data-bulk-apply class="btn-primary text-sm py-1.5" disabled>Apply</button>
      </div>
    </form>

    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="text-left text-gray-500 bg-gray-50">
          <tr>
            <th class="px-5 py-3 font-medium w-10">
              <input type="checkbox" form="reviewsBulkForm" data-bulk-select-all class="accent-primary h-4 w-4" title="Select all" aria-label="Select all" />
            </th>
            <th class="px-5 py-3 font-medium">Product</th>
            <th class="px-5 py-3 font-medium">Review</th>
            <th class="px-5 py-3 font-medium">Rating</th>
            <th class="px-5 py-3 font-medium">Status</th>
            <th class="px-5 py-3 font-medium text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @forelse($reviews as $review)
            <tr class="hover:bg-gray-50 align-top">
              <td class="px-5 py-4">
                <input type="checkbox" form="reviewsBulkForm" name="ids[]" value="{{ $review->id }}" data-bulk-row class="accent-primary h-4 w-4" aria-label="Select review" />
              </td>
              <td class="px-5 py-4">
                @if($review->product)
                  <div class="flex items-center gap-3 min-w-[180px]">
                    <img src="{{ $review->product->imageUrl() }}" class="h-11 w-11 rounded-lg object-cover bg-gray-100" alt="">
                    <div>
                      <a href="{{ route('admin.products.edit', $review->product) }}" class="font-medium hover:text-brand-700">{{ $review->product->name }}</a>
                      <p class="text-xs text-gray-400">{{ $review->created_at?->format('d M Y, H:i') }}</p>
                    </div>
                  </div>
                @else
                  <span class="text-gray-400">Deleted product</span>
                @endif
              </td>
              <td class="px-5 py-4 max-w-md">
                <p class="font-semibold text-ink">{{ $review->author_name }}
                  @if($review->is_verified_purchase)
                    <span class="ml-1 text-[10px] font-bold uppercase tracking-wide text-brand-700 bg-brand-50 px-1.5 py-0.5 rounded">Verified</span>
                  @endif
                </p>
                @if($review->author_email)<p class="text-xs text-gray-400">{{ $review->author_email }}</p>@endif
                @if($review->title)<p class="mt-1 font-medium">{{ $review->title }}</p>@endif
                <p class="mt-1 text-gray-600 whitespace-pre-line">{{ $review->body }}</p>
              </td>
              <td class="px-5 py-4 whitespace-nowrap">
                <span class="text-amber-500 tracking-tight">{{ str_repeat("\u{2605}", $review->rating) }}{{ str_repeat("\u{2606}", 5 - $review->rating) }}</span>
              </td>
              <td class="px-5 py-4">
                @if($review->status === 'approved')
                  <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-700">Approved</span>
                @elseif($review->status === 'rejected')
                  <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-600">Rejected</span>
                @else
                  <span class="px-2 py-1 text-xs rounded-full bg-amber-100 text-amber-700">Pending</span>
                @endif
              </td>
              <td class="px-5 py-4 text-right whitespace-nowrap">
                @if($review->status !== 'approved')
                  <form method="POST" action="{{ route('admin.reviews.approve', $review) }}" class="inline">@csrf
                    <button class="px-2 py-1 text-xs rounded bg-brand-50 text-brand-700 border border-brand-200">Approve</button>
                  </form>
                @endif
                @if($review->status !== 'rejected')
                  <form method="POST" action="{{ route('admin.reviews.reject', $review) }}" class="inline">@csrf
                    <button class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-600">Reject</button>
                  </form>
                @endif
                <form method="POST" action="{{ route('admin.reviews.destroy', $review) }}" class="inline" onsubmit="return confirm('Delete this review permanently?')">@csrf @method('DELETE')
                  <button class="px-2 py-1 text-xs rounded bg-red-50 text-red-600 border border-red-200">Delete</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="px-5 py-12 text-center text-gray-400">No reviews in this filter.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  @if($reviews->hasPages())
    <div>{{ $reviews->links() }}</div>
  @endif
</div>
@endsection
