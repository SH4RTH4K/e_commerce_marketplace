@extends('layouts.admin')
@php $editing = $feature->exists; @endphp
@section('title', $editing ? 'Edit Feature' : 'New Feature')

@section('content')
<form method="POST" action="{{ $editing ? route('admin.features.update', $feature) : route('admin.features.store') }}">
  @csrf
  @if($editing) @method('PUT') @endif

  <div class="flex items-center justify-between mb-6">
    <div>
      <a href="{{ route('admin.features.index') }}" class="text-sm text-gray-500 hover:text-primary">&larr; Back</a>
      <h2 class="text-xl font-bold mt-1">{{ $editing ? 'Edit Feature' : 'New Feature' }}</h2>
    </div>
    <button class="btn-primary">Save</button>
  </div>

  <div class="max-w-2xl card p-5 space-y-4">
    <div class="grid grid-cols-2 gap-4">
      <div><label class="lbl">Title</label><input name="title" class="inp" value="{{ old('title', $feature->title) }}" required /></div>
      <div><label class="lbl">Position</label><input name="position" type="number" class="inp" value="{{ old('position', $feature->position ?? 0) }}" /></div>
    </div>
    <div><label class="lbl">Subtitle</label><input name="subtitle" class="inp" value="{{ old('subtitle', $feature->subtitle) }}" /></div>
    <div>
      <label class="lbl">Icon (SVG path data — the <code>d=""</code> attribute)</label>
      <textarea name="icon" class="inp font-mono text-xs" rows="3">{{ old('icon', $feature->icon) }}</textarea>
      <p class="text-xs text-gray-400 mt-1">Example: <code>M12 3l8 4v5c0 5-3.5 7.5-8 9-4.5-1.5-8-4-8-9V7z</code></p>
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" class="accent-primary" @checked(old('is_active', $feature->is_active ?? true)) /> Active (show on homepage)</label>
  </div>
</form>
@endsection
