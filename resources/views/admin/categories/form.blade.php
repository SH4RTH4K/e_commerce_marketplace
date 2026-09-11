@extends('layouts.admin')
@php $editing = $category->exists; @endphp
@section('title', $editing ? 'Edit Category' : 'New Category')

@section('content')
<form method="POST" action="{{ $editing ? route('admin.categories.update', $category) : route('admin.categories.store') }}" enctype="multipart/form-data">
  @csrf
  @if($editing) @method('PUT') @endif

  <div class="flex items-center justify-between mb-6">
    <div>
      <a href="{{ route('admin.categories.index') }}" class="text-sm text-gray-500 hover:text-primary">&larr; Back</a>
      <h2 class="text-xl font-bold mt-1">{{ $editing ? 'Edit Category' : 'New Category' }}</h2>
    </div>
    <button class="btn-primary">Save</button>
  </div>

  <div class="max-w-2xl space-y-6">
    <div class="card p-5 space-y-4">
      <h3 class="font-semibold">Details</h3>
      <div class="grid grid-cols-[1fr_100px] gap-4">
        <div><label class="lbl">Name</label><input name="name" class="inp" value="{{ old('name', $category->name) }}" required /></div>
        <div><label class="lbl">Icon (emoji)</label><input name="icon" class="inp" value="{{ old('icon', $category->icon) }}" placeholder="👕" /></div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div><label class="lbl">Slug (blank = auto)</label><input name="slug" class="inp" value="{{ old('slug', $category->slug) }}" /></div>
        <div><label class="lbl">Position</label><input name="position" type="number" class="inp" value="{{ old('position', $category->position ?? 0) }}" /></div>
      </div>
      <div><label class="lbl">Description</label><textarea name="description" class="inp" rows="2">{{ old('description', $category->description) }}</textarea></div>
      <div>
        <label class="lbl">Image</label>
        <div class="flex items-center gap-3">
          @if($category->image)<img src="{{ $category->imageUrl() }}" class="h-14 w-14 rounded-lg object-cover" alt="">@endif
          <input name="image_file" type="file" accept="image/*" class="text-sm" />
        </div>
      </div>
      <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" class="accent-primary" @checked(old('is_active', $category->is_active ?? true)) /> Active (visible on storefront)</label>
    </div>

    <div class="card p-5 space-y-3">
      <h3 class="font-semibold">SEO</h3>
      <div><label class="lbl">Meta title</label><input name="meta_title" class="inp" value="{{ old('meta_title', $category->meta_title) }}" /></div>
      <div><label class="lbl">Meta description</label><textarea name="meta_description" class="inp" rows="2">{{ old('meta_description', $category->meta_description) }}</textarea></div>
      <div><label class="lbl">Meta keywords (comma-separated)</label><textarea name="meta_keywords" class="inp" rows="2">{{ old('meta_keywords', $category->meta_keywords) }}</textarea></div>
    </div>
  </div>
</form>
@endsection
