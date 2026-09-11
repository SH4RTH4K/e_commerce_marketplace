@extends('layouts.admin')
@section('title', 'Messages')

@section('content')
<div class="space-y-6 min-w-0">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div class="min-w-0">
      <h2 class="text-xl font-bold text-ink">Contact messages</h2>
      <p class="text-sm text-neutral-500 mt-1">Inquiries submitted from the website contact form. Visitor email is shown for each message.</p>
    </div>
    <a href="{{ route('admin.contact-fields.index') }}" class="text-sm font-semibold text-primary hover:underline shrink-0">Manage form fields →</a>
  </div>

  <div class="flex flex-wrap gap-2">
    @foreach(['new' => 'New', 'read' => 'Read', 'archived' => 'Archived', 'all' => 'All'] as $key => $label)
      <a href="{{ route('admin.contact-messages.index', array_filter(['status' => $key, 'q' => $q])) }}"
         class="px-3 py-1.5 rounded-lg text-sm font-semibold {{ $status === $key ? 'bg-primary text-white' : 'bg-white border border-neutral-200 text-neutral-600 hover:bg-primary/5' }}">
        {{ $label }}
        <span class="ml-1 opacity-80">{{ $counts[$key] ?? 0 }}</span>
      </a>
    @endforeach
  </div>

  <form method="GET" class="flex flex-wrap gap-2 w-full">
    <input type="hidden" name="status" value="{{ $status }}" />
    <input name="q" value="{{ $q }}" placeholder="Search name, email, phone, subject…" class="inp w-full sm:max-w-sm min-w-0 flex-1" />
    <button class="btn-primary shrink-0">Search</button>
  </form>

  <div class="card overflow-hidden min-w-0">
    <form id="messagesBulkForm" method="POST" action="{{ route('admin.contact-messages.bulk') }}" data-bulk-form>
      @csrf
      <div data-bulk-bar class="px-3 sm:px-4 py-3 border-b border-neutral-200 bg-primary/5 flex flex-wrap items-center gap-2 sm:gap-3">
        <span class="text-sm font-medium text-ink shrink-0"><span data-bulk-count>0</span> selected</span>
        <select name="bulk_action" class="border border-neutral-300 rounded-lg px-3 py-1.5 text-sm bg-white min-w-0 max-w-full">
          <option value="">Choose action…</option>
          <option value="read">Mark read</option>
          <option value="archive">Archive</option>
          <option value="delete" data-confirm="Delete :count selected message(s) permanently?">Delete</option>
        </select>
        <button type="submit" data-bulk-apply class="btn-primary text-sm py-1.5 shrink-0" disabled>Apply</button>
      </div>
    </form>

    <p class="sm:hidden px-3 py-2 text-[11px] text-neutral-400 border-b border-neutral-100">Swipe sideways to see all columns</p>

    <div class="overflow-x-auto overscroll-x-contain" style="-webkit-overflow-scrolling: touch;">
      <table class="w-full text-sm min-w-[720px]">
        <thead class="text-left text-neutral-500 bg-neutral-50">
          <tr>
            <th class="px-3 sm:px-5 py-3 font-medium w-10">
              <input type="checkbox" form="messagesBulkForm" data-bulk-select-all class="accent-primary h-4 w-4" title="Select all" aria-label="Select all" />
            </th>
            <th class="px-3 sm:px-5 py-3 font-medium whitespace-nowrap">From</th>
            <th class="px-3 sm:px-5 py-3 font-medium whitespace-nowrap">Email</th>
            <th class="px-3 sm:px-5 py-3 font-medium whitespace-nowrap">Subject</th>
            <th class="px-3 sm:px-5 py-3 font-medium whitespace-nowrap">Status</th>
            <th class="px-3 sm:px-5 py-3 font-medium text-right whitespace-nowrap">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-100">
          @forelse($messages as $message)
            <tr class="hover:bg-neutral-50 {{ $message->status === 'new' ? 'bg-primary/[0.03]' : '' }}">
              <td class="px-3 sm:px-5 py-3 sm:py-4">
                <input type="checkbox" form="messagesBulkForm" name="ids[]" value="{{ $message->id }}" data-bulk-row class="accent-primary h-4 w-4" aria-label="Select message" />
              </td>
              <td class="px-3 sm:px-5 py-3 sm:py-4 min-w-[140px]">
                <p class="font-semibold text-ink">{{ $message->name ?: '—' }}</p>
                @if($message->phone)<p class="text-xs text-neutral-400">{{ $message->phone }}</p>@endif
                <p class="text-xs text-neutral-400 whitespace-nowrap">{{ $message->created_at?->format('d M Y, H:i') }}</p>
              </td>
              <td class="px-3 sm:px-5 py-3 sm:py-4 min-w-[160px]">
                @if($message->email)
                  <a href="mailto:{{ $message->email }}" class="font-medium text-primary hover:underline break-all">{{ $message->email }}</a>
                @else
                  <span class="text-neutral-400">—</span>
                @endif
              </td>
              <td class="px-3 sm:px-5 py-3 sm:py-4 min-w-[160px] max-w-xs">
                <a href="{{ route('admin.contact-messages.show', $message) }}" class="font-medium hover:text-primary line-clamp-2">{{ $message->displaySubject() }}</a>
              </td>
              <td class="px-3 sm:px-5 py-3 sm:py-4 whitespace-nowrap">
                @if($message->status === 'new')
                  <span class="px-2 py-1 text-xs rounded-lg bg-amber-100 text-amber-700">New</span>
                @elseif($message->status === 'archived')
                  <span class="px-2 py-1 text-xs rounded-lg bg-neutral-100 text-neutral-600">Archived</span>
                @else
                  <span class="px-2 py-1 text-xs rounded-lg bg-emerald-100 text-emerald-700">Read</span>
                @endif
              </td>
              <td class="px-3 sm:px-5 py-3 sm:py-4 text-right">
                <div class="inline-flex flex-wrap gap-1 justify-end">
                  <a href="{{ route('admin.contact-messages.show', $message) }}" class="px-2 py-1 text-xs rounded bg-neutral-100 text-neutral-600">Open</a>
                  @if($message->status === 'new')
                    <form method="POST" action="{{ route('admin.contact-messages.read', $message) }}" class="inline">@csrf
                      <button class="px-2 py-1 text-xs rounded bg-primary/10 text-primary border border-primary/20">Mark read</button>
                    </form>
                  @endif
                  @if($message->status !== 'archived')
                    <form method="POST" action="{{ route('admin.contact-messages.archive', $message) }}" class="inline">@csrf
                      <button class="px-2 py-1 text-xs rounded bg-neutral-100 text-neutral-600">Archive</button>
                    </form>
                  @endif
                  <form method="POST" action="{{ route('admin.contact-messages.destroy', $message) }}" class="inline" onsubmit="return confirm('Delete this message permanently?')">@csrf @method('DELETE')
                    <button class="px-2 py-1 text-xs rounded bg-red-50 text-red-600 border border-red-200">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="px-5 py-12 text-center text-neutral-400">No messages in this filter.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  @if($messages->hasPages())
    <div class="overflow-x-auto">{{ $messages->links() }}</div>
  @endif
</div>
@endsection
