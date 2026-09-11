@extends('layouts.admin')
@php $editing = $field->exists; @endphp
@section('title', $editing ? 'Edit Field' : 'New Field')

@section('content')
<form method="POST" action="{{ $editing ? route('admin.contact-fields.update', $field) : route('admin.contact-fields.store') }}">
  @csrf
  @if($editing) @method('PUT') @endif

  <div class="flex items-center justify-between mb-6">
    <div>
      <a href="{{ route('admin.contact-fields.index') }}" class="text-sm text-neutral-500 hover:text-primary">&larr; Back</a>
      <h2 class="text-xl font-bold mt-1">{{ $editing ? 'Edit field' : 'Add field' }}</h2>
    </div>
    <button class="btn-primary">Save</button>
  </div>

  <div class="max-w-2xl card p-5 space-y-4">
    <div class="grid sm:grid-cols-2 gap-4">
      <div class="sm:col-span-2">
        <label class="lbl">Label</label>
        <input name="label" class="inp" value="{{ old('label', $field->label) }}" required />
      </div>
      <div>
        <label class="lbl">Type</label>
        <select name="type" id="fieldType" class="inp" @disabled($field->is_system) required>
          @foreach($types as $value => $label)
            <option value="{{ $value }}" @selected(old('type', $field->type) === $value)>{{ $label }}</option>
          @endforeach
        </select>
        @if($field->is_system)
          <input type="hidden" name="type" value="{{ $field->type }}" />
          <p class="text-xs text-neutral-400 mt-1">Default field type is locked.</p>
        @endif
      </div>
      <div>
        <label class="lbl">Sort position</label>
        <input name="position" type="number" min="0" class="inp" value="{{ old('position', $field->position ?? 0) }}" />
      </div>
      <div class="sm:col-span-2">
        <label class="lbl">Placeholder</label>
        <input name="placeholder" class="inp" value="{{ old('placeholder', $field->placeholder) }}" />
      </div>
      <div class="sm:col-span-2">
        <label class="lbl">Help text</label>
        <input name="help_text" class="inp" value="{{ old('help_text', $field->help_text) }}" />
      </div>
    </div>

    <div id="optionsBlock" class="{{ old('type', $field->type) === 'select' ? '' : 'hidden' }}">
      <label class="lbl">Dropdown options (one per line)</label>
      <textarea name="options_text" class="inp" rows="4" placeholder="Order status&#10;Product question&#10;Other">{{ old('options_text', implode("\n", $field->optionList())) }}</textarea>
    </div>

    <div id="fileBlock" class="grid sm:grid-cols-2 gap-4 {{ old('type', $field->type) === 'file' ? '' : 'hidden' }}">
      <div>
        <label class="lbl">Accepted files</label>
        <input name="accept" class="inp" value="{{ old('accept', $field->accept) }}" placeholder=".pdf,.jpg,.png" />
      </div>
      <div>
        <label class="lbl">Max size (KB)</label>
        <input name="max_size_kb" type="number" min="64" max="10240" class="inp" value="{{ old('max_size_kb', $field->max_size_kb ?? 4096) }}" />
      </div>
    </div>

    <div class="flex flex-wrap gap-4">
      <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_required" value="1" class="accent-primary" @checked(old('is_required', $field->is_required)) @disabled($field->is_system && in_array($field->key, ['name','email','message'], true)) />
        Required
      </label>
      <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_active" value="1" class="accent-primary" @checked(old('is_active', $field->is_active ?? true)) />
        Active on contact page
      </label>
    </div>
  </div>
</form>

@push('scripts')
<script>
(function () {
  var type = document.getElementById('fieldType');
  var options = document.getElementById('optionsBlock');
  var file = document.getElementById('fileBlock');
  function sync() {
    var v = type ? type.value : @json($field->type);
    if (options) options.classList.toggle('hidden', v !== 'select');
    if (file) file.classList.toggle('hidden', v !== 'file');
  }
  if (type) type.addEventListener('change', sync);
  sync();
})();
</script>
@endpush
@endsection
