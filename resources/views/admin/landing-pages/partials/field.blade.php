@php
  $name = $field['name'];
  $type = $field['type'];
  $label = $field['label'];
  $val = $c[$name] ?? '';
@endphp

@if($type === 'page_title')
  <div>
    <label class="lbl">{{ $label }} *</label>
    <input name="title" class="inp" value="{{ old('title', $page->title) }}" required />
  </div>

@elseif($type === 'page_slug')
  <div>
    <label class="lbl">{{ $label }}</label>
    <div class="flex items-center gap-2">
      <span class="text-xs text-gray-400 shrink-0">/lp/</span>
      <input name="slug" class="inp font-mono" value="{{ old('slug', $page->slug) }}" />
    </div>
  </div>

@elseif($type === 'page_product')
  <div>
    <label class="lbl">{{ $label }} *</label>
    <select name="product_id" class="inp" required>
      @foreach($products as $p)
        <option value="{{ $p->id }}" @selected((string) old('product_id', $page->product_id) === (string) $p->id)>
          {{ $p->name }} @if($p->sku)— {{ $p->sku }}@endif
        </option>
      @endforeach
    </select>
  </div>

@elseif($type === 'page_active')
  <label class="flex items-center gap-2 text-sm font-medium">
    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $page->is_active)) class="accent-primary" />
    {{ $label }} on the public site
  </label>

@elseif($type === 'textarea')
  <div>
    <label class="lbl">{{ $label }}</label>
    <textarea name="{{ $name }}" class="inp" rows="3">{{ old($name, $val) }}</textarea>
  </div>

@elseif($type === 'number')
  <div>
    <label class="lbl">{{ $label }}</label>
    <input type="number" step="0.01" name="{{ $name }}" class="inp" value="{{ old($name, $val) }}" />
  </div>

@elseif($type === 'image')
  @php $previewUrl = $val ? $page->mediaUrl($val) : ''; @endphp
  <div class="lp-image-field" data-image-field="{{ $name }}">
    <label class="lbl">{{ $label }}</label>
    <div class="mb-2 flex items-center gap-3">
      <div class="lp-image-preview shrink-0 h-20 w-28 rounded-lg border border-gray-200 bg-gray-50 overflow-hidden flex items-center justify-center {{ $previewUrl ? '' : 'border-dashed' }}">
        @if($previewUrl)
          <img src="{{ $previewUrl }}" alt="" class="h-full w-full object-cover" data-lp-preview-img />
        @else
          <span class="lp-image-placeholder text-[10px] text-gray-400 px-2 text-center" data-lp-preview-placeholder>No image</span>
          <img src="" alt="" class="hidden h-full w-full object-cover" data-lp-preview-img />
        @endif
      </div>
      <label class="text-xs text-red-600 flex items-center gap-1 {{ $previewUrl ? '' : 'hidden' }}" data-lp-remove-wrap>
        <input type="checkbox" name="remove_{{ $name }}" value="1" data-lp-remove /> Remove current
      </label>
    </div>
    <input type="file" name="{{ $name }}_file" accept="image/*" class="inp" data-lp-file />
    <p class="text-xs text-gray-400 mt-1">Or paste an image URL:</p>
    <input type="url" name="{{ $name }}_url" class="inp mt-1" value="" placeholder="https://…" data-lp-url />
  </div>

@elseif($type === 'features_list')
  @php $rows = old('features', $c['features'] ?? [['title'=>'','body'=>'']]); @endphp
  <div>
    <label class="lbl">{{ $label }}</label>
    <div class="space-y-3" data-repeat="features">
      @foreach($rows as $i => $row)
        <div class="lp-repeat-row relative grid sm:grid-cols-2 gap-2 p-3 pt-8 rounded-lg border border-gray-100 bg-gray-50/50">
          <button type="button" class="lp-remove-row absolute top-2 right-2 text-xs font-semibold text-red-600 hover:text-red-700">Remove</button>
          <input name="features[{{ $i }}][title]" class="inp" placeholder="Title" value="{{ $row['title'] ?? '' }}" />
          <input name="features[{{ $i }}][body]" class="inp" placeholder="Body" value="{{ $row['body'] ?? '' }}" />
        </div>
      @endforeach
    </div>
    <button type="button" class="mt-2 text-sm font-semibold text-primary" data-add-row="features" data-template="feature">+ Add feature</button>
  </div>

@elseif($type === 'benefits_icon_list')
  @php $rows = old('benefits', $c['benefits'] ?? [['icon'=>'✨','title'=>'','body'=>'']]); @endphp
  <div>
    <label class="lbl">{{ $label }}</label>
    <div class="space-y-3" data-repeat="benefits">
      @foreach($rows as $i => $row)
        <div class="lp-repeat-row relative grid sm:grid-cols-6 gap-2 p-3 pt-8 rounded-lg border border-gray-100 bg-gray-50/50">
          <button type="button" class="lp-remove-row absolute top-2 right-2 text-xs font-semibold text-red-600 hover:text-red-700">Remove</button>
          <input name="benefits[{{ $i }}][icon]" class="inp sm:col-span-1" placeholder="Icon" value="{{ $row['icon'] ?? '' }}" />
          <input name="benefits[{{ $i }}][title]" class="inp sm:col-span-2" placeholder="Title" value="{{ $row['title'] ?? '' }}" />
          <input name="benefits[{{ $i }}][body]" class="inp sm:col-span-3" placeholder="Body" value="{{ $row['body'] ?? '' }}" />
        </div>
      @endforeach
    </div>
    <button type="button" class="mt-2 text-sm font-semibold text-primary" data-add-row="benefits" data-template="benefit-icon">+ Add benefit</button>
  </div>

@elseif($type === 'benefits_items_list')
  @php $rows = old('benefits', $c['benefits'] ?? [['title'=>'','items'=>'']]); @endphp
  <div>
    <label class="lbl">{{ $label }}</label>
    <div class="space-y-3" data-repeat="benefits">
      @foreach($rows as $i => $row)
        <div class="lp-repeat-row relative grid gap-2 p-3 pt-8 rounded-lg border border-gray-100 bg-gray-50/50">
          <button type="button" class="lp-remove-row absolute top-2 right-2 text-xs font-semibold text-red-600 hover:text-red-700">Remove</button>
          <input name="benefits[{{ $i }}][title]" class="inp" placeholder="Column title" value="{{ $row['title'] ?? '' }}" />
          <textarea name="benefits[{{ $i }}][items]" class="inp" rows="3" placeholder="One item per line">{{ $row['items'] ?? '' }}</textarea>
        </div>
      @endforeach
    </div>
    <button type="button" class="mt-2 text-sm font-semibold text-primary" data-add-row="benefits" data-template="benefit-items">+ Add column</button>
  </div>

@elseif($type === 'testimonials_list')
  @php $rows = old('testimonials', $c['testimonials'] ?? [['text'=>'','image'=>'']]); @endphp
  <div>
    <label class="lbl">{{ $label }}</label>
    <div class="space-y-3" data-repeat="testimonials">
      @foreach($rows as $i => $row)
        <div class="lp-repeat-row relative grid sm:grid-cols-2 gap-3 p-3 pt-8 rounded-lg border border-gray-100 bg-gray-50/50">
          <button type="button" class="lp-remove-row absolute top-2 right-2 text-xs font-semibold text-red-600 hover:text-red-700">Remove</button>
          <div>
            <textarea name="testimonials[{{ $i }}][text]" class="inp" rows="3" placeholder="Quote">{{ $row['text'] ?? '' }}</textarea>
          </div>
          <div class="lp-row-image" data-lp-row-image>
            <div class="lp-image-preview mb-2 h-16 w-24 rounded border border-gray-200 bg-gray-50 overflow-hidden flex items-center justify-center {{ !empty($row['image']) ? '' : 'border-dashed' }}">
              @if(!empty($row['image']))
                <img src="{{ $page->mediaUrl($row['image']) }}" alt="" class="h-full w-full object-cover" data-lp-preview-img />
              @else
                <span class="lp-image-placeholder text-[10px] text-gray-400" data-lp-preview-placeholder>No image</span>
                <img src="" alt="" class="hidden h-full w-full object-cover" data-lp-preview-img />
              @endif
            </div>
            <label class="text-xs text-red-600 flex items-center gap-1 mb-1 {{ !empty($row['image']) ? '' : 'hidden' }}" data-lp-remove-wrap>
              <input type="checkbox" name="testimonials[{{ $i }}][remove_image]" value="1" data-lp-remove /> Remove image
            </label>
            @if(!empty($row['image']))
              <input type="hidden" name="testimonials[{{ $i }}][image]" value="{{ $row['image'] }}" data-lp-image-path />
            @endif
            <input type="file" name="testimonials[{{ $i }}][image_file]" accept="image/*" class="inp text-xs" data-lp-file />
            <input type="url" name="testimonials[{{ $i }}][image_url]" class="inp mt-1 text-xs" placeholder="Or image URL" data-lp-url />
          </div>
        </div>
      @endforeach
    </div>
    <button type="button" class="mt-2 text-sm font-semibold text-primary" data-add-row="testimonials" data-template="testimonial">+ Add review</button>
  </div>

@elseif($type === 'catalog_list')
  @php $rows = old('catalog', $c['catalog'] ?? [['name'=>'','price_label'=>'','image'=>'']]); @endphp
  <div>
    <label class="lbl">{{ $label }}</label>
    <div class="space-y-3" data-repeat="catalog">
      @foreach($rows as $i => $row)
        <div class="lp-repeat-row relative grid sm:grid-cols-3 gap-2 p-3 pt-8 rounded-lg border border-gray-100 bg-gray-50/50">
          <button type="button" class="lp-remove-row absolute top-2 right-2 text-xs font-semibold text-red-600 hover:text-red-700">Remove</button>
          <input name="catalog[{{ $i }}][name]" class="inp" placeholder="Name" value="{{ $row['name'] ?? '' }}" />
          <input name="catalog[{{ $i }}][price_label]" class="inp" placeholder="Price label" value="{{ $row['price_label'] ?? '' }}" />
          <div class="lp-row-image" data-lp-row-image>
            <div class="lp-image-preview mb-1 h-12 w-12 rounded border border-gray-200 bg-gray-50 overflow-hidden flex items-center justify-center {{ !empty($row['image']) ? '' : 'border-dashed' }}">
              @if(!empty($row['image']))
                <img src="{{ $page->mediaUrl($row['image']) }}" alt="" class="h-full w-full object-cover" data-lp-preview-img />
              @else
                <span class="lp-image-placeholder text-[10px] text-gray-400" data-lp-preview-placeholder>No image</span>
                <img src="" alt="" class="hidden h-full w-full object-cover" data-lp-preview-img />
              @endif
            </div>
            <label class="text-xs text-red-600 flex items-center gap-1 mb-1 {{ !empty($row['image']) ? '' : 'hidden' }}" data-lp-remove-wrap>
              <input type="checkbox" name="catalog[{{ $i }}][remove_image]" value="1" data-lp-remove /> Remove image
            </label>
            @if(!empty($row['image']))
              <input type="hidden" name="catalog[{{ $i }}][image]" value="{{ $row['image'] }}" data-lp-image-path />
            @endif
            <input type="file" name="catalog[{{ $i }}][image_file]" accept="image/*" class="inp text-xs" data-lp-file />
            <input type="url" name="catalog[{{ $i }}][image_url]" class="inp mt-1 text-xs" placeholder="Or image URL" data-lp-url />
          </div>
        </div>
      @endforeach
    </div>
    <button type="button" class="mt-2 text-sm font-semibold text-primary" data-add-row="catalog" data-template="catalog">+ Add item</button>
  </div>

@else
  <div>
    <label class="lbl">{{ $label }}</label>
    <input name="{{ $name }}" class="inp" value="{{ old($name, $val) }}" />
  </div>
@endif
