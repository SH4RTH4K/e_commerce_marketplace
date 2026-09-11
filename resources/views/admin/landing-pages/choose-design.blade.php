@extends('layouts.admin')
@section('title', 'Choose design')

@section('content')
<div class="space-y-6 max-w-4xl">
  <div>
    <a href="{{ route('admin.landing-pages.index') }}" class="text-sm text-gray-500 hover:text-primary">&larr; Back</a>
    <h2 class="text-xl font-bold mt-1">Choose a landing page design</h2>
    <p class="text-sm text-gray-500 mt-1">Pick a template, then fill in your product and copy. Use Preview to see the live layout; turn visibility off to hide a design from new pages.</p>
  </div>

  @if(session('status'))
    <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('status') }}</div>
  @endif
  @if($errors->any())
    <div class="rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">{{ $errors->first() }}</div>
  @endif

  <div class="grid sm:grid-cols-2 gap-5">
    @foreach($designs as $key => $meta)
      <div class="card p-5 border border-gray-100 {{ empty($meta['visible']) ? 'opacity-75' : '' }}">
        <div class="aspect-[16/10] rounded-xl overflow-hidden bg-gray-100 mb-4 relative border border-gray-100">
          <img
            src="{{ $meta['preview_url'] }}"
            alt="{{ $meta['label'] }} preview"
            class="absolute inset-0 h-full w-full object-cover object-top"
            loading="lazy"
          />
          @if(empty($meta['visible']))
            <span class="absolute top-2 left-2 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide rounded bg-gray-900/70 text-white">Hidden</span>
          @endif
        </div>

        <div class="flex items-start justify-between gap-3 mb-2">
          <h3 class="font-semibold text-lg text-ink">{{ $meta['label'] }}</h3>
          <form method="POST" action="{{ route('admin.landing-pages.designs.toggle', $key) }}" class="shrink-0 flex items-center gap-2" title="Toggle design visibility">
            @csrf
            @method('PATCH')
            <span class="text-xs font-medium text-gray-500">{{ !empty($meta['visible']) ? 'Visible' : 'Hidden' }}</span>
            <button type="submit" role="switch" aria-checked="{{ !empty($meta['visible']) ? 'true' : 'false' }}"
              aria-label="{{ !empty($meta['visible']) ? 'Hide' : 'Show' }} {{ $meta['label'] }} design"
              class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-primary/40 {{ !empty($meta['visible']) ? 'bg-primary' : 'bg-gray-300' }}">
              <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition {{ !empty($meta['visible']) ? 'translate-x-5' : 'translate-x-0.5' }}"></span>
            </button>
          </form>
        </div>

        <p class="text-sm text-gray-500 mt-1">{{ $meta['description'] }}</p>

        <div class="mt-4 flex flex-wrap items-center gap-2">
          <a href="{{ route('admin.landing-pages.designs.preview', $key) }}" target="_blank" rel="noopener"
             class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            Preview
          </a>
          @if(!empty($meta['visible']))
            <a href="{{ route('admin.landing-pages.create', ['design' => $key]) }}"
               class="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline ml-auto">
              Use this design →
            </a>
          @else
            <span class="text-sm text-gray-400 ml-auto">Turn visibility on to use</span>
          @endif
        </div>
      </div>
    @endforeach
  </div>
</div>
@endsection
