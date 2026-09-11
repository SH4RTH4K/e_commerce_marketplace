@extends('layouts.admin')
@section('title', 'Landing Pages')

@section('content')
<div class="space-y-5 sm:space-y-6">
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div class="min-w-0">
      <h2 class="text-xl font-bold">Landing Pages</h2>
      <p class="text-sm text-gray-500 mt-1 leading-relaxed">Create sales pages from a design, link a product, and send visitors to store checkout.</p>
    </div>
    <a href="{{ route('admin.landing-pages.create') }}" class="btn-primary w-full sm:w-auto shrink-0 text-center">+ New landing page</a>
  </div>

  @if(session('status'))
    <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('status') }}</div>
  @endif

  {{-- Mobile cards --}}
  <div class="space-y-3 lg:hidden">
    @forelse($pages as $page)
      <article class="card p-4 {{ $page->is_active ? '' : 'opacity-80' }}">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0 flex-1">
            <h3 class="font-semibold text-ink leading-snug break-words">{{ $page->title }}</h3>
            <p class="text-xs text-gray-400 mt-1">Updated {{ $page->updated_at?->diffForHumans() }}</p>
          </div>
          @if($page->is_active)
            <span class="shrink-0 px-2 py-1 text-[11px] font-medium rounded-full bg-green-100 text-green-700">Visible</span>
          @else
            <span class="shrink-0 px-2 py-1 text-[11px] font-medium rounded-full bg-gray-100 text-gray-500">Hidden</span>
          @endif
        </div>

        <dl class="mt-3 grid grid-cols-1 gap-2 text-sm">
          <div class="flex items-baseline justify-between gap-3">
            <dt class="text-gray-400 shrink-0">Design</dt>
            <dd><span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-700">{{ $page->designLabel() }}</span></dd>
          </div>
          <div class="flex items-baseline justify-between gap-3">
            <dt class="text-gray-400 shrink-0">Product</dt>
            <dd class="text-right min-w-0">
              @if($page->product)
                <a href="{{ route('admin.products.edit', $page->product) }}" class="hover:text-primary break-words">{{ $page->product->name }}</a>
              @else
                <span class="text-gray-400">—</span>
              @endif
            </dd>
          </div>
          <div class="flex items-baseline justify-between gap-3">
            <dt class="text-gray-400 shrink-0">URL</dt>
            <dd class="text-right min-w-0">
              <a href="{{ $page->publicUrl() }}" target="_blank" class="font-mono text-xs text-primary hover:underline break-all">/lp/{{ $page->slug }}</a>
            </dd>
          </div>
        </dl>

        <div class="mt-4 pt-3 border-t border-gray-100 grid grid-cols-2 gap-2">
          <form method="POST" action="{{ route('admin.landing-pages.toggle', $page) }}">@csrf @method('PATCH')
            <button type="submit" class="w-full px-3 py-2.5 text-xs font-medium rounded-lg {{ $page->is_active ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-green-50 text-green-700 border border-green-200' }}">
              {{ $page->is_active ? 'Hide' : 'Show' }}
            </button>
          </form>
          <a href="{{ $page->publicUrl() }}" target="_blank" class="w-full px-3 py-2.5 text-xs font-medium rounded-lg bg-gray-100 text-gray-700 text-center">Open</a>
          <a href="{{ route('admin.landing-pages.edit', $page) }}" class="w-full px-3 py-2.5 text-xs font-medium rounded-lg bg-primary/10 text-primary text-center">Edit</a>
          <form method="POST" action="{{ route('admin.landing-pages.destroy', $page) }}" onsubmit="return confirm('Delete this landing page?')">@csrf @method('DELETE')
            <button type="submit" class="w-full px-3 py-2.5 text-xs font-medium rounded-lg bg-red-50 text-red-600 border border-red-200">Delete</button>
          </form>
        </div>
      </article>
    @empty
      <div class="card px-5 py-12 text-center text-gray-400 text-sm">No landing pages yet. Create one to get started.</div>
    @endforelse
  </div>

  {{-- Desktop table --}}
  <div class="card overflow-hidden hidden lg:block">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="text-left text-gray-500 bg-gray-50">
          <tr>
            <th class="px-5 py-3 font-medium">Page</th>
            <th class="px-5 py-3 font-medium">Design</th>
            <th class="px-5 py-3 font-medium">Product</th>
            <th class="px-5 py-3 font-medium">URL</th>
            <th class="px-5 py-3 font-medium">Visibility</th>
            <th class="px-5 py-3 font-medium text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @forelse($pages as $page)
            <tr class="hover:bg-gray-50 {{ $page->is_active ? '' : 'opacity-70' }}">
              <td class="px-5 py-4">
                <p class="font-medium">{{ $page->title }}</p>
                <p class="text-xs text-gray-400 mt-0.5">Updated {{ $page->updated_at?->diffForHumans() }}</p>
              </td>
              <td class="px-5 py-4">
                <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-700">{{ $page->designLabel() }}</span>
              </td>
              <td class="px-5 py-4">
                @if($page->product)
                  <a href="{{ route('admin.products.edit', $page->product) }}" class="hover:text-primary">{{ $page->product->name }}</a>
                @else
                  <span class="text-gray-400">—</span>
                @endif
              </td>
              <td class="px-5 py-4">
                <a href="{{ $page->publicUrl() }}" target="_blank" class="font-mono text-xs text-primary hover:underline break-all">/lp/{{ $page->slug }}</a>
              </td>
              <td class="px-5 py-4">
                @if($page->is_active)
                  <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-700">Visible</span>
                @else
                  <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-500">Hidden</span>
                @endif
              </td>
              <td class="px-5 py-4 text-right whitespace-nowrap">
                <form method="POST" action="{{ route('admin.landing-pages.toggle', $page) }}" class="inline">@csrf @method('PATCH')
                  <button class="px-2 py-1 text-xs rounded {{ $page->is_active ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-green-50 text-green-700 border border-green-200' }}">
                    {{ $page->is_active ? 'Hide' : 'Show' }}
                  </button>
                </form>
                <a href="{{ $page->publicUrl() }}" target="_blank" class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-600">Open</a>
                <a href="{{ route('admin.landing-pages.edit', $page) }}" class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-600">Edit</a>
                <form method="POST" action="{{ route('admin.landing-pages.destroy', $page) }}" class="inline" onsubmit="return confirm('Delete this landing page?')">@csrf @method('DELETE')
                  <button class="px-2 py-1 text-xs rounded bg-red-50 text-red-600 border border-red-200">Delete</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="px-5 py-12 text-center text-gray-400">No landing pages yet. Create one to get started.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  @if($pages->hasPages())
    <div class="overflow-x-auto">{{ $pages->links() }}</div>
  @endif
</div>
@endsection
