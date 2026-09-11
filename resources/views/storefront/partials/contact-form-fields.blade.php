@php
  $fields = $fields ?? collect();
  $inputClass = $inputClass ?? 'w-full border border-gray-300 rounded-md px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-600/30 bg-white';
  $buttonClass = $buttonClass ?? 'bg-brand-600 hover:bg-brand-700 text-white font-semibold px-6 py-3 rounded-md';
  $labelClass = $labelClass ?? 'block text-sm text-gray-600 mb-1';
@endphp

@if($fields->isEmpty())
  <p class="text-sm text-gray-500">The contact form is not configured yet.</p>
@else
  <form method="POST" action="{{ route('contact.store') }}" enctype="multipart/form-data" class="grid sm:grid-cols-2 gap-4">
    @csrf
    @foreach($fields as $field)
      @php
        $name = 'fields['.$field->key.']';
        $id = 'cf_'.$field->key;
        $old = old('fields.'.$field->key);
        $wide = in_array($field->type, ['textarea', 'file', 'select', 'checkbox'], true) || in_array($field->key, ['subject', 'message'], true);
      @endphp
      <div class="{{ $wide ? 'sm:col-span-2' : '' }}">
        @if($field->type !== 'checkbox')
          <label for="{{ $id }}" class="{{ $labelClass }}">
            {{ $field->label }}
            @if($field->is_required)<span class="text-brand-600">*</span>@endif
          </label>
        @endif

        @if($field->type === 'textarea')
          <textarea id="{{ $id }}" name="{{ $name }}" rows="5" @required($field->is_required) placeholder="{{ $field->placeholder }}" class="{{ $inputClass }}">{{ $old }}</textarea>
        @elseif($field->type === 'select')
          <select id="{{ $id }}" name="{{ $name }}" @required($field->is_required) class="{{ $inputClass }}">
            <option value="">{{ $field->placeholder ?: 'Select…' }}</option>
            @foreach($field->optionList() as $opt)
              <option value="{{ $opt }}" @selected($old === $opt)>{{ $opt }}</option>
            @endforeach
          </select>
        @elseif($field->type === 'checkbox')
          <label for="{{ $id }}" class="inline-flex items-start gap-2 text-sm font-semibold text-gray-700">
            <input id="{{ $id }}" type="checkbox" name="{{ $name }}" value="1" @checked(old('fields.'.$field->key)) @required($field->is_required) class="mt-0.5 accent-brand-600" />
            <span>{{ $field->label }}@if($field->is_required)<span class="text-brand-600">*</span>@endif</span>
          </label>
        @elseif($field->type === 'file')
          <input id="{{ $id }}" type="file" name="{{ $name }}" @required($field->is_required) @if($field->accept) accept="{{ $field->accept }}" @endif class="block w-full text-sm text-gray-600" />
        @else
          <input
            id="{{ $id }}"
            type="{{ $field->type === 'tel' ? 'tel' : ($field->type === 'email' ? 'email' : 'text') }}"
            name="{{ $name }}"
            value="{{ $old }}"
            @required($field->is_required)
            placeholder="{{ $field->placeholder }}"
            class="{{ $inputClass }}"
          />
        @endif

        @if($field->help_text)
          <p class="text-xs text-gray-400 mt-1">{{ $field->help_text }}</p>
        @elseif($field->type === 'file' && $field->max_size_kb)
          <p class="text-xs text-gray-400 mt-1">Max size {{ number_format($field->max_size_kb / 1024, 1) }} MB</p>
        @endif
        @error('fields.'.$field->key)
          <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror
      </div>
    @endforeach

    <div class="sm:col-span-2">
      <button type="submit" class="{{ $buttonClass }}">Send message</button>
    </div>
  </form>
@endif
