@extends('layouts.admin')
@php $editing = $banner->exists; @endphp
@section('title', $editing ? 'Edit Banner' : 'New Banner')

@section('content')
<form method="POST" action="{{ $editing ? route('admin.banners.update', $banner) : route('admin.banners.store') }}" enctype="multipart/form-data">
  @csrf
  @if($editing) @method('PUT') @endif

  <div class="flex items-center justify-between mb-6">
    <div>
      <a href="{{ route('admin.banners.index') }}" class="text-sm text-gray-500 hover:text-primary">&larr; Back</a>
      <h2 class="text-xl font-bold mt-1">{{ $editing ? 'Edit Banner' : 'New Banner' }}</h2>
    </div>
    <button class="btn-primary">Save</button>
  </div>

  <div class="max-w-2xl card p-5 space-y-4">
    <div class="grid sm:grid-cols-2 gap-4">
      <div class="sm:col-span-2">
        <label class="lbl">Placement</label>
        <select name="placement" class="inp">
          @foreach($placements as $value => $label)
            <option value="{{ $value }}" @selected(old('placement', $banner->placement) === $value)>{{ $label }}</option>
          @endforeach
        </select>
        <p class="text-xs text-gray-400 mt-1">Hero banners become homepage slider slides. Use Sort position for slide order.</p>
      </div>
      <div>
        <label class="lbl">Sort position</label>
        <input name="position" type="number" class="inp" value="{{ old('position', $banner->position ?? 0) }}" min="0" />
        <p class="text-xs text-gray-400 mt-1">Lower numbers appear first within the same placement.</p>
      </div>
      <input type="hidden" name="style" value="{{ old('style', $banner->style ?? 'brand') }}" />
    </div>

    <div><label class="lbl">Title</label><input name="title" class="inp" value="{{ old('title', $banner->title) }}" /></div>
    <div><label class="lbl">Subtitle</label><textarea name="subtitle" class="inp" rows="2">{{ old('subtitle', $banner->subtitle) }}</textarea></div>
    <div class="grid grid-cols-2 gap-4">
      <div><label class="lbl">Badge</label><input name="badge" class="inp" value="{{ old('badge', $banner->badge) }}" placeholder="Opening Sale" /></div>
      <div><label class="lbl">Button text</label><input name="button_text" class="inp" value="{{ old('button_text', $banner->button_text) }}" placeholder="Shop now" /></div>
      <div class="col-span-2"><label class="lbl">Link URL</label><input name="link_url" class="inp" value="{{ old('link_url', $banner->link_url) }}" placeholder="/shop" /></div>
    </div>

    <div>
      <label class="lbl">Image</label>
      <div class="flex items-center gap-3">
        @if($banner->image)<img src="{{ $banner->imageUrl() }}" class="h-14 w-24 rounded-lg object-cover" alt="">@endif
        <input name="image_file" type="file" accept="image/*" class="text-sm" />
      </div>
      <input name="image_url" class="inp mt-2" value="{{ old('image_url') }}" placeholder="…or paste an image URL" />
      @if($banner->image)
        <label class="flex items-center gap-2 text-xs text-red-500 mt-2"><input type="checkbox" name="remove_image" value="1" class="accent-red-500" /> Remove current image</label>
      @endif
    </div>

    <label class="flex items-center gap-2 text-sm font-medium">
      <input type="checkbox" name="is_active" value="1" class="accent-primary" @checked(old('is_active', $banner->is_active ?? true)) />
      Visible on storefront
    </label>
    <p class="text-xs text-gray-400 -mt-2">When off, this banner and its section slot are removed from the homepage.</p>
  </div>
</form>
@endsection
