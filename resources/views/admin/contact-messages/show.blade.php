@extends('layouts.admin')
@section('title', 'Message #'.$message->id)

@section('content')
<div class="space-y-5 sm:space-y-6 max-w-3xl">
  <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between">
    <div class="min-w-0">
      <a href="{{ route('admin.contact-messages.index') }}" class="text-sm text-neutral-500 hover:text-primary">&larr; Back to messages</a>
      <h2 class="text-lg sm:text-xl font-bold mt-1 break-words">{{ $message->displaySubject() }}</h2>
      <p class="text-sm text-neutral-500 mt-1">Received {{ $message->created_at?->format('d M Y, H:i') }}</p>
    </div>
    <div class="flex flex-wrap gap-2 w-full sm:w-auto">
      @if($message->status !== 'archived')
        <form method="POST" action="{{ route('admin.contact-messages.archive', $message) }}" class="flex-1 sm:flex-none">@csrf
          <button class="w-full px-3 py-2 sm:py-1.5 text-sm rounded-lg bg-neutral-100 text-neutral-700">Archive</button>
        </form>
      @endif
      <form method="POST" action="{{ route('admin.contact-messages.destroy', $message) }}" class="flex-1 sm:flex-none" onsubmit="return confirm('Delete this message permanently?')">@csrf @method('DELETE')
        <button class="w-full px-3 py-2 sm:py-1.5 text-sm rounded-lg bg-red-50 text-red-600 border border-red-200">Delete</button>
      </form>
    </div>
  </div>

  <div class="card p-4 sm:p-5 space-y-4">
    <h3 class="font-semibold text-ink">Visitor contact details</h3>
    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
      <div>
        <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Name</dt>
        <dd class="mt-1 font-medium">{{ $message->name ?: '—' }}</dd>
      </div>
      <div>
        <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Email</dt>
        <dd class="mt-1">
          @if($message->email)
            <a href="mailto:{{ $message->email }}" class="font-medium text-primary hover:underline break-all">{{ $message->email }}</a>
          @else
            —
          @endif
        </dd>
      </div>
      <div>
        <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Phone</dt>
        <dd class="mt-1 font-medium">
          @if($message->phone)
            <a href="tel:{{ preg_replace('/\s+/', '', $message->phone) }}" class="hover:text-primary">{{ $message->phone }}</a>
          @else
            —
          @endif
        </dd>
      </div>
      <div>
        <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Status</dt>
        <dd class="mt-1 capitalize font-medium">{{ $message->status }}</dd>
      </div>
    </dl>
  </div>

  <div class="card p-4 sm:p-5 space-y-4">
    <h3 class="font-semibold text-ink">Submitted answers</h3>
    <div class="divide-y divide-neutral-100">
      @foreach($message->values as $row)
        <div class="py-3 first:pt-0 last:pb-0">
          <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">{{ $row->field_label }}</p>
          <div class="mt-1 text-sm text-ink whitespace-pre-line">
            @if($row->isFile())
              <a href="{{ $row->fileUrl() }}" target="_blank" rel="noopener" class="text-primary font-semibold hover:underline">{{ $row->displayValue() }}</a>
              @if($row->isImage())
                <button
                  type="button"
                  class="mt-2 block rounded-lg border border-neutral-200 overflow-hidden bg-neutral-50 hover:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/30"
                  data-image-preview
                  data-src="{{ $row->fileUrl() }}"
                  data-name="{{ $row->displayValue() }}"
                  title="Click to expand"
                >
                  <img
                    src="{{ $row->fileUrl() }}"
                    alt="{{ $row->displayValue() }}"
                    class="h-24 w-24 object-cover"
                    loading="lazy"
                  />
                </button>
              @endif
            @else
              {{ $row->displayValue() !== '' ? $row->displayValue() : '—' }}
            @endif
          </div>
        </div>
      @endforeach
    </div>
  </div>

  <div class="card p-4 sm:p-5 text-xs text-neutral-400 space-y-1">
    <p>IP: {{ $message->ip_address ?: '—' }}</p>
    <p class="break-all">User agent: {{ $message->user_agent ?: '—' }}</p>
  </div>
</div>

{{-- Expanded image viewer --}}
<div id="imagePreviewModal" class="fixed inset-0 z-[80] hidden items-center justify-center p-4 bg-black/70" role="dialog" aria-modal="true" aria-hidden="true">
  <button type="button" class="absolute inset-0 cursor-zoom-out" data-image-preview-close aria-label="Close"></button>
  <div class="relative z-10 max-w-5xl w-full max-h-[90vh] flex flex-col items-center gap-3">
    <div class="flex w-full items-center justify-between gap-3 text-white text-sm">
      <p id="imagePreviewName" class="truncate font-medium"></p>
      <button type="button" class="shrink-0 rounded-lg bg-white/15 hover:bg-white/25 px-3 py-1.5" data-image-preview-close>Close</button>
    </div>
    <img id="imagePreviewImg" src="" alt="" class="max-h-[80vh] max-w-full rounded-lg object-contain shadow-2xl bg-white/5" />
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  var modal = document.getElementById('imagePreviewModal');
  var img = document.getElementById('imagePreviewImg');
  var nameEl = document.getElementById('imagePreviewName');
  if (!modal || !img) return;

  function openPreview(src, name) {
    img.src = src;
    img.alt = name || '';
    if (nameEl) nameEl.textContent = name || '';
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function closePreview() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    modal.setAttribute('aria-hidden', 'true');
    img.removeAttribute('src');
    document.body.style.overflow = '';
  }

  document.querySelectorAll('[data-image-preview]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openPreview(btn.getAttribute('data-src'), btn.getAttribute('data-name'));
    });
  });

  modal.querySelectorAll('[data-image-preview-close]').forEach(function (el) {
    el.addEventListener('click', closePreview);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) closePreview();
  });
})();
</script>
@endpush
