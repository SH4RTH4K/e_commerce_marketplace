@extends('layouts.admin')
@section('title', 'Banners')

@section('content')
<div class="space-y-6">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h2 class="text-xl font-bold">Homepage banners</h2>
      <p class="text-sm text-gray-500 mt-1">Control every banner on the storefront. Hidden banners are removed from the page — layouts adjust automatically.</p>
    </div>
    <a href="{{ route('admin.banners.create') }}" class="btn-primary">+ New banner</a>
  </div>

  @foreach($placements as $key => $label)
    @php $group = $banners->where('placement', $key); @endphp
    <div class="card p-5">
      <div class="flex items-center justify-between gap-3 mb-4">
        <div>
          <h3 class="font-bold text-sm uppercase tracking-wide text-gray-500">{{ explode(' — ', $label)[0] }}</h3>
          <p class="text-xs text-gray-400 mt-0.5">{{ \Illuminate\Support\Str::after($label, ' — ') }}</p>
        </div>
        <span class="text-xs text-gray-400">{{ $group->where('is_active', true)->count() }} visible · {{ $group->count() }} total</span>
      </div>

      @if($group->isEmpty())
        <p class="text-sm text-gray-400 py-4 text-center border border-dashed border-gray-200 rounded-lg">No banners in this slot — section hidden on homepage.</p>
      @else
        <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
          @foreach($group as $banner)
            <div class="border border-gray-100 rounded-xl overflow-hidden {{ $banner->is_active ? '' : 'opacity-60' }}">
              <div class="h-32 bg-gray-900 relative">
                @if($banner->image)
                  <img src="{{ $banner->imageUrl() }}" class="h-full w-full object-cover opacity-70" alt="">
                @else
                  <div class="h-full w-full bg-gradient-to-br from-brand-600 to-brand-800 opacity-80"></div>
                @endif
                <div class="absolute inset-0 p-3 flex flex-col justify-end bg-gradient-to-t from-black/60 to-transparent">
                  @if($banner->badge)<span class="w-fit bg-primary text-white text-[10px] font-bold px-2 py-0.5 rounded">{{ $banner->badge }}</span>@endif
                  <p class="text-white font-bold text-sm mt-1 line-clamp-2">{{ $banner->title ?: 'Untitled banner' }}</p>
                </div>
                <span class="absolute top-2 right-2 text-[10px] font-bold px-2 py-0.5 rounded {{ $banner->is_active ? 'bg-green-500 text-white' : 'bg-gray-600 text-white' }}">{{ $banner->is_active ? 'Visible' : 'Hidden' }}</span>
              </div>
              <div class="p-3 flex items-center justify-between gap-2 text-xs">
                <span class="text-gray-400">Pos {{ $banner->position }} &middot; {{ $banner->styleLabel() }}</span>
                <div class="flex items-center gap-1 whitespace-nowrap">
                  <form method="POST" action="{{ route('admin.banners.toggle', $banner) }}">@csrf @method('PATCH')
                    <button type="submit" class="px-2 py-1 rounded {{ $banner->is_active ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-green-50 text-green-700 border border-green-200' }}">{{ $banner->is_active ? 'Hide' : 'Show' }}</button>
                  </form>
                  <a href="{{ route('admin.banners.edit', $banner) }}" class="px-2 py-1 rounded bg-gray-100 text-gray-600">Edit</a>
                  <form method="POST" action="{{ route('admin.banners.destroy', $banner) }}" class="inline" onsubmit="return confirm('Delete this banner?')">@csrf @method('DELETE')<button class="px-2 py-1 rounded bg-red-50 text-red-600 border border-red-200">Del</button></form>
                </div>
              </div>
            </div>
          @endforeach
        </div>
      @endif
    </div>
  @endforeach
</div>
@endsection
