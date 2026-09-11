@extends('layouts.admin')
@php
  $designLabel = $designs[$page->design]['label'] ?? $page->design;
  $saved = session('saved_section');
@endphp
@section('title', 'Edit Landing Page')

@section('content')
<div class="space-y-6 max-w-4xl mx-auto w-full">
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div>
      <a href="{{ route('admin.landing-pages.index') }}" class="text-sm text-gray-500 hover:text-primary">&larr; Back</a>
      <h2 class="text-xl font-bold mt-1">{{ $page->title }}</h2>
      <p class="text-sm text-gray-500 mt-1">
        Design: <strong>{{ $designLabel }}</strong>
        · <a href="{{ $page->publicUrl() }}" target="_blank" rel="noopener" class="text-primary hover:underline">Preview page</a>
        · <span class="font-mono text-xs">/lp/{{ $page->slug }}</span>
      </p>
    </div>
    <div class="flex flex-wrap items-center gap-3">
      <form method="POST" action="{{ route('admin.landing-pages.toggle', $page) }}" id="lpPageVisibilityForm" class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 shadow-sm">
        @csrf
        @method('PATCH')
        <span class="text-xs font-medium text-gray-500">Page visibility</span>
        <button type="submit"
          role="switch"
          id="lpPageVisibilitySwitch"
          class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors {{ $page->is_active ? 'bg-green-500' : 'bg-gray-300' }}"
          aria-checked="{{ $page->is_active ? 'true' : 'false' }}"
          aria-label="Turn landing page {{ $page->is_active ? 'off' : 'on' }}">
          <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition {{ $page->is_active ? 'translate-x-5' : 'translate-x-0.5' }}"></span>
        </button>
        <span id="lpPageVisibilityLabel" class="text-xs font-semibold {{ $page->is_active ? 'text-green-700' : 'text-gray-500' }}">
          {{ $page->is_active ? 'On' : 'Off' }}
        </span>
      </form>
      <button type="button" id="saveAllSettings" class="btn-primary shrink-0">Save all</button>
    </div>
  </div>

  @if(session('status') && ! session('saved_section'))
    <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('status') }}</div>
  @endif
  @if($errors->any())
    <div class="rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">
      <ul class="list-disc pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif
  <div id="lpSaveAllFeedback" class="hidden text-sm rounded-xl px-4 py-3"></div>

  <p class="text-sm text-gray-500">Sections are listed in the same order as the public preview page (top to bottom). Save all at once, or save each block on its own. Turn a section off to hide it on the public page.</p>

  <nav class="lp-section-nav sticky top-16 z-10 -mx-1 px-1 py-2 flex flex-wrap gap-2 text-xs bg-gray-100/95 backdrop-blur-sm">
    @foreach($sections as $key => $meta)
      <a href="#{{ $key }}" data-lp-jump="{{ $key }}" class="px-2.5 py-1 rounded-lg border {{ $saved === $key ? 'border-primary bg-primary/10 text-primary' : 'border-gray-200 text-gray-600 hover:bg-gray-50' }}">{{ $meta['label'] }}</a>
    @endforeach
  </nav>

  @foreach($sections as $sectionKey => $meta)
    @php
      $sectionOn = $page->sectionVisible($sectionKey);
      $highlight = $saved === $sectionKey;
    @endphp
    <section id="{{ $sectionKey }}" class="lp-section card overflow-hidden scroll-mt-40 {{ $highlight ? 'ring-2 ring-primary/40 lp-section-just-saved' : '' }}">
      <form method="POST" action="{{ route('admin.landing-pages.sections.update', [$page, $sectionKey]) }}" enctype="multipart/form-data" class="lp-section-form divide-y divide-gray-100" data-section="{{ $sectionKey }}">
        @csrf
        @method('PUT')

        <div class="px-5 py-4 flex flex-wrap items-center justify-between gap-3 bg-gray-50/80">
          <div class="min-w-0">
            <h3 class="font-semibold text-ink">{{ $meta['label'] }}</h3>
            <div class="section-feedback mt-1.5 text-sm rounded-lg px-3 py-1.5 {{ $highlight ? 'lp-pulse-msg bg-green-50 border border-green-200 text-green-800' : 'hidden' }}">
              @if($highlight)
                {{ session('status') ?: (($meta['label'] ?? $sectionKey).' saved.') }}
              @endif
            </div>
          </div>
          <div class="flex items-center gap-3 shrink-0">
            @if(!empty($meta['toggle']))
              <label class="flex items-center gap-2 text-sm">
                <span class="text-xs font-medium text-gray-500">{{ $sectionOn ? 'Visible' : 'Hidden' }}</span>
                <button type="button" role="switch"
                  class="lp-section-switch relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors {{ $sectionOn ? 'bg-primary' : 'bg-gray-300' }}"
                  data-checked="{{ $sectionOn ? '1' : '0' }}"
                  aria-checked="{{ $sectionOn ? 'true' : 'false' }}"
                  aria-label="Toggle {{ $meta['label'] }} visibility">
                  <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition {{ $sectionOn ? 'translate-x-5' : 'translate-x-0.5' }}"></span>
                </button>
                <input type="checkbox" name="section_visible" value="1" class="sr-only lp-section-visible" @checked($sectionOn) />
              </label>
            @endif
            <button type="submit" class="section-save-btn btn-primary text-sm py-1.5 px-3">Save {{ $meta['label'] }}</button>
          </div>
        </div>

        <div class="p-5 space-y-4 {{ !empty($meta['toggle']) && ! $sectionOn ? 'opacity-60' : '' }}">
          @foreach($meta['fields'] as $field)
            @include('admin.landing-pages.partials.field', [
              'field' => $field,
              'page' => $page,
              'c' => $c,
              'products' => $products,
            ])
          @endforeach
        </div>
      </form>
    </section>
  @endforeach
</div>

<style>
  @keyframes lp-pulse-msg {
    0%, 100% { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.35); transform: scale(1); }
    50% { box-shadow: 0 0 0 8px rgba(22, 163, 74, 0); transform: scale(1.02); }
  }
  @keyframes lp-pulse-card {
    0%, 100% { box-shadow: 0 0 0 0 rgba(196, 123, 10, 0.25); }
    50% { box-shadow: 0 0 0 6px rgba(196, 123, 10, 0); }
  }
  .lp-pulse-msg {
    display: inline-block;
    animation: lp-pulse-msg 1s ease-in-out 3;
  }
  .lp-section-just-saved {
    animation: lp-pulse-card 1s ease-in-out 2;
  }
</style>
@endsection

@push('scripts')
<script>
(function () {
  function jumpOffset() {
    var topbar = document.querySelector('.admin-main > header');
    var nav = document.querySelector('.lp-section-nav');
    var pad = 20; // land a little above the section
    return (topbar ? topbar.offsetHeight : 64) + (nav ? nav.offsetHeight : 0) + pad;
  }

  function scrollToSection(id, smooth) {
    var el = document.getElementById(id);
    if (!el) return;
    var top = el.getBoundingClientRect().top + window.pageYOffset - jumpOffset();
    window.scrollTo({ top: Math.max(0, top), behavior: smooth ? 'smooth' : 'auto' });
  }

  document.querySelectorAll('[data-lp-jump]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      var id = a.getAttribute('data-lp-jump');
      if (!id || !document.getElementById(id)) return;
      e.preventDefault();
      scrollToSection(id, true);
      if (history.replaceState) history.replaceState(null, '', '#' + id);
    });
  });

  var hash = (location.hash || '').replace(/^#/, '');
  if (hash) {
    requestAnimationFrame(function () { scrollToSection(hash, false); });
  } else if (@json($saved)) {
    requestAnimationFrame(function () { scrollToSection(@json($saved), false); });
  }

  document.querySelectorAll('.lp-section-switch').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var box = btn.parentElement.querySelector('.lp-section-visible');
      if (!box) return;
      box.checked = !box.checked;
      var on = box.checked;
      btn.setAttribute('aria-checked', on ? 'true' : 'false');
      btn.classList.toggle('bg-primary', on);
      btn.classList.toggle('bg-gray-300', !on);
      var knob = btn.querySelector('span');
      if (knob) {
        knob.classList.toggle('translate-x-5', on);
        knob.classList.toggle('translate-x-0.5', !on);
      }
      var label = btn.parentElement.querySelector('span.text-xs');
      if (label) label.textContent = on ? 'Visible' : 'Hidden';
    });
  });

  var removeBtnHtml = '<button type="button" class="lp-remove-row absolute top-2 right-2 text-xs font-semibold text-red-600 hover:text-red-700">Remove</button>';

  var templates = {
    feature: function (i) {
      return '<div class="lp-repeat-row relative grid sm:grid-cols-2 gap-2 p-3 pt-8 rounded-lg border border-gray-100 bg-gray-50/50">' +
        removeBtnHtml +
        '<input name="features[' + i + '][title]" class="inp" placeholder="Title" />' +
        '<input name="features[' + i + '][body]" class="inp" placeholder="Body" /></div>';
    },
    'benefit-icon': function (i) {
      return '<div class="lp-repeat-row relative grid sm:grid-cols-6 gap-2 p-3 pt-8 rounded-lg border border-gray-100 bg-gray-50/50">' +
        removeBtnHtml +
        '<input name="benefits[' + i + '][icon]" class="inp sm:col-span-1" placeholder="Icon" value="✨" />' +
        '<input name="benefits[' + i + '][title]" class="inp sm:col-span-2" placeholder="Title" />' +
        '<input name="benefits[' + i + '][body]" class="inp sm:col-span-3" placeholder="Body" /></div>';
    },
    'benefit-items': function (i) {
      return '<div class="lp-repeat-row relative grid gap-2 p-3 pt-8 rounded-lg border border-gray-100 bg-gray-50/50">' +
        removeBtnHtml +
        '<input name="benefits[' + i + '][title]" class="inp" placeholder="Column title" />' +
        '<textarea name="benefits[' + i + '][items]" class="inp" rows="3" placeholder="One item per line"></textarea></div>';
    },
    testimonial: function (i) {
      return '<div class="lp-repeat-row relative grid sm:grid-cols-2 gap-3 p-3 pt-8 rounded-lg border border-gray-100 bg-gray-50/50">' +
        removeBtnHtml +
        '<textarea name="testimonials[' + i + '][text]" class="inp" rows="3" placeholder="Quote"></textarea>' +
        '<div class="lp-row-image" data-lp-row-image>' +
          '<div class="lp-image-preview mb-2 h-16 w-24 rounded border border-dashed border-gray-200 bg-gray-50 overflow-hidden flex items-center justify-center">' +
            '<span class="lp-image-placeholder text-[10px] text-gray-400" data-lp-preview-placeholder>No image</span>' +
            '<img src="" alt="" class="hidden h-full w-full object-cover" data-lp-preview-img />' +
          '</div>' +
          '<label class="text-xs text-red-600 flex items-center gap-1 mb-1 hidden" data-lp-remove-wrap>' +
            '<input type="checkbox" name="testimonials[' + i + '][remove_image]" value="1" data-lp-remove /> Remove image' +
          '</label>' +
          '<input type="file" name="testimonials[' + i + '][image_file]" accept="image/*" class="inp text-xs" data-lp-file />' +
          '<input type="url" name="testimonials[' + i + '][image_url]" class="inp mt-1 text-xs" placeholder="Or image URL" data-lp-url />' +
        '</div></div>';
    },
    catalog: function (i) {
      return '<div class="lp-repeat-row relative grid sm:grid-cols-3 gap-2 p-3 pt-8 rounded-lg border border-gray-100 bg-gray-50/50">' +
        removeBtnHtml +
        '<input name="catalog[' + i + '][name]" class="inp" placeholder="Name" />' +
        '<input name="catalog[' + i + '][price_label]" class="inp" placeholder="Price label" />' +
        '<div class="lp-row-image" data-lp-row-image>' +
          '<div class="lp-image-preview mb-1 h-12 w-12 rounded border border-dashed border-gray-200 bg-gray-50 overflow-hidden flex items-center justify-center">' +
            '<span class="lp-image-placeholder text-[10px] text-gray-400" data-lp-preview-placeholder>No image</span>' +
            '<img src="" alt="" class="hidden h-full w-full object-cover" data-lp-preview-img />' +
          '</div>' +
          '<label class="text-xs text-red-600 flex items-center gap-1 mb-1 hidden" data-lp-remove-wrap>' +
            '<input type="checkbox" name="catalog[' + i + '][remove_image]" value="1" data-lp-remove /> Remove image' +
          '</label>' +
          '<input type="file" name="catalog[' + i + '][image_file]" accept="image/*" class="inp text-xs" data-lp-file />' +
          '<input type="url" name="catalog[' + i + '][image_url]" class="inp mt-1 text-xs" placeholder="Or image URL" data-lp-url />' +
        '</div></div>';
    }
  };

  function reindexRepeat(wrap) {
    var key = wrap.getAttribute('data-repeat');
    if (!key) return;
    Array.from(wrap.children).forEach(function (row, i) {
      row.querySelectorAll('[name]').forEach(function (el) {
        el.name = el.name.replace(new RegExp('^' + key + '\\[\\d+\\]'), key + '[' + i + ']');
      });
    });
  }

  document.querySelectorAll('[data-add-row]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var key = btn.getAttribute('data-add-row');
      var tpl = btn.getAttribute('data-template');
      var wrap = btn.parentElement.querySelector('[data-repeat="' + key + '"]');
      if (!wrap || !templates[tpl]) return;
      var i = wrap.children.length;
      wrap.insertAdjacentHTML('beforeend', templates[tpl](i));
    });
  });

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.lp-remove-row');
    if (!btn) return;
    var row = btn.closest('.lp-repeat-row');
    var wrap = row && row.parentElement;
    if (!row || !wrap || !wrap.hasAttribute('data-repeat')) return;
    row.remove();
    reindexRepeat(wrap);
  });

  var csrf = document.querySelector('meta[name="csrf-token"]');
  csrf = csrf ? csrf.getAttribute('content') : '';

  function setPreview(root, url) {
    if (!root) return;
    var box = root.querySelector('.lp-image-preview');
    var img = root.querySelector('[data-lp-preview-img]');
    var ph = root.querySelector('[data-lp-preview-placeholder]');
    var removeWrap = root.querySelector('[data-lp-remove-wrap]');
    if (!img) return;
    if (url) {
      img.src = url;
      img.classList.remove('hidden');
      if (ph) ph.classList.add('hidden');
      if (box) box.classList.remove('border-dashed');
      if (removeWrap) removeWrap.classList.remove('hidden');
    } else {
      img.removeAttribute('src');
      img.classList.add('hidden');
      if (ph) ph.classList.remove('hidden');
      if (box) box.classList.add('border-dashed');
      if (removeWrap) {
        removeWrap.classList.add('hidden');
        var rm = removeWrap.querySelector('[data-lp-remove]');
        if (rm) rm.checked = false;
      }
    }
  }

  function bindImagePreview(root) {
    if (!root || root.dataset.lpPreviewBound === '1') return;
    root.dataset.lpPreviewBound = '1';
    var file = root.querySelector('[data-lp-file]');
    var urlInput = root.querySelector('[data-lp-url]');
    var remove = root.querySelector('[data-lp-remove]');
    var objectUrl = null;

    function revoke() {
      if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = null;
      }
    }

    if (file) {
      file.addEventListener('change', function () {
        revoke();
        if (file.files && file.files[0]) {
          objectUrl = URL.createObjectURL(file.files[0]);
          setPreview(root, objectUrl);
          if (urlInput) urlInput.value = '';
          if (remove) remove.checked = false;
        }
      });
    }

    if (urlInput) {
      urlInput.addEventListener('input', function () {
        var v = (urlInput.value || '').trim();
        if (v) {
          revoke();
          if (file) file.value = '';
          setPreview(root, v);
          if (remove) remove.checked = false;
        }
      });
    }

    if (remove) {
      remove.addEventListener('change', function () {
        if (remove.checked) {
          revoke();
          if (file) file.value = '';
          if (urlInput) urlInput.value = '';
          setPreview(root, '');
        }
      });
    }
  }

  function bindAllImagePreviews(scope) {
    (scope || document).querySelectorAll('.lp-image-field, [data-lp-row-image]').forEach(bindImagePreview);
  }

  bindAllImagePreviews();

  // Re-bind when new repeat rows are added
  document.querySelectorAll('[data-add-row]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      setTimeout(function () {
        var key = btn.getAttribute('data-add-row');
        var wrap = btn.parentElement.querySelector('[data-repeat="' + key + '"]');
        if (wrap && wrap.lastElementChild) bindAllImagePreviews(wrap.lastElementChild);
      }, 0);
    });
  });

  function applySavedImages(form, images) {
    if (!images) return;

    function syncRowImage(root, info, hiddenName) {
      if (!root) return;
      var url = (info && info.url) || '';
      var path = (info && info.path) || '';
      setPreview(root, url);
      var file = root.querySelector('[data-lp-file]');
      var urlInput = root.querySelector('[data-lp-url]');
      if (file) file.value = '';
      if (urlInput) urlInput.value = '';
      var remove = root.querySelector('[data-lp-remove]');
      if (remove) remove.checked = false;
      var pathInput = root.querySelector('[data-lp-image-path]');
      if (path) {
        if (!pathInput) {
          pathInput = document.createElement('input');
          pathInput.type = 'hidden';
          pathInput.setAttribute('data-lp-image-path', '');
          root.insertBefore(pathInput, root.querySelector('[data-lp-file]'));
        }
        pathInput.name = hiddenName;
        pathInput.value = path;
      } else if (pathInput) {
        pathInput.remove();
      }
    }

    Object.keys(images.fields || {}).forEach(function (name) {
      var root = form.querySelector('[data-image-field="' + name + '"]');
      var info = images.fields[name] || {};
      if (!root) return;
      var url = info.url || '';
      if (url) url += (url.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now();
      setPreview(root, url);
      var file = root.querySelector('[data-lp-file]');
      var urlInput = root.querySelector('[data-lp-url]');
      if (file) file.value = '';
      if (urlInput) urlInput.value = '';
      var remove = root.querySelector('[data-lp-remove]');
      if (remove) remove.checked = false;
    });

    form.querySelectorAll('[data-repeat="testimonials"] .lp-repeat-row').forEach(function (row, i) {
      var info = (images.testimonials && (images.testimonials[i] || images.testimonials[String(i)])) || {};
      if (info.url) info = { path: info.path, url: info.url + (info.url.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now() };
      syncRowImage(row.querySelector('[data-lp-row-image]'), info, 'testimonials[' + i + '][image]');
    });

    form.querySelectorAll('[data-repeat="catalog"] .lp-repeat-row').forEach(function (row, i) {
      var info = (images.catalog && (images.catalog[i] || images.catalog[String(i)])) || {};
      if (info.url) info = { path: info.path, url: info.url + (info.url.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now() };
      syncRowImage(row.querySelector('[data-lp-row-image]'), info, 'catalog[' + i + '][image]');
    });
  }

  function showFeedback(el, kind, msg) {
    if (!el) return;
    el.classList.remove('hidden', 'lp-pulse-msg', 'bg-green-50', 'border-green-200', 'text-green-800', 'bg-red-50', 'border-red-200', 'text-red-700');
    // restart pulse animation
    void el.offsetWidth;
    if (kind === 'ok') {
      el.classList.add('bg-green-50', 'border', 'border-green-200', 'text-green-800', 'lp-pulse-msg');
    } else {
      el.classList.add('bg-red-50', 'border', 'border-red-200', 'text-red-700');
    }
    el.textContent = msg;
  }

  function pulseSectionCard(form) {
    var card = form.closest('.lp-section');
    if (!card) return;
    card.classList.remove('lp-section-just-saved', 'ring-2', 'ring-primary/40');
    void card.offsetWidth;
    card.classList.add('lp-section-just-saved', 'ring-2', 'ring-primary/40');
  }

  function formatErrors(err) {
    if (!err || typeof err !== 'object') return 'Save failed.';
    if (err.message) return err.message;
    if (err.errors) {
      return Object.keys(err.errors).map(function (k) {
        return (err.errors[k] || []).join(' ');
      }).filter(Boolean).join(' ') || 'Validation failed.';
    }
    return 'Save failed.';
  }

  function saveForm(form) {
    var btn = form.querySelector('.section-save-btn');
    var feedback = form.querySelector('.section-feedback');
    var defaultLabel = btn ? btn.textContent : '';

    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Saving…';
    }

    return fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrf,
      },
      credentials: 'same-origin',
    })
      .then(function (res) {
        return res.text().then(function (text) {
          var data = {};
          if (text) {
            try { data = JSON.parse(text); }
            catch (e) {
              throw { message: res.ok ? 'Unexpected server response.' : ('Save failed (HTTP ' + res.status + ').') };
            }
          }
          if (!res.ok) throw data;
          return data;
        });
      })
      .then(function (data) {
        showFeedback(feedback, 'ok', data.message || 'Saved.');
        pulseSectionCard(form);
        applySavedImages(form, data.images);
        form.querySelectorAll('input[type="file"]').forEach(function (input) { input.value = ''; });
        return data;
      })
      .catch(function (err) {
        showFeedback(feedback, 'err', formatErrors(err));
        throw err;
      })
      .finally(function () {
        if (btn) {
          btn.disabled = false;
          btn.textContent = defaultLabel;
        }
      });
  }

  document.querySelectorAll('.lp-section-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      saveForm(form).catch(function () {});
    });
  });

  var saveAllBtn = document.getElementById('saveAllSettings');
  var saveAllFeedback = document.getElementById('lpSaveAllFeedback');
  if (saveAllBtn) {
    saveAllBtn.addEventListener('click', function () {
      var forms = Array.from(document.querySelectorAll('.lp-section-form'));
      var defaultLabel = saveAllBtn.textContent;
      saveAllBtn.disabled = true;
      saveAllBtn.textContent = 'Saving all…';
      showFeedback(saveAllFeedback, 'ok', 'Saving all sections…');

      forms.reduce(function (chain, form) {
        return chain.then(function () { return saveForm(form); });
      }, Promise.resolve())
        .then(function () {
          showFeedback(saveAllFeedback, 'ok', 'All sections saved.');
        })
        .catch(function () {
          showFeedback(saveAllFeedback, 'err', 'Some sections failed to save. Check the section errors below.');
        })
        .finally(function () {
          saveAllBtn.disabled = false;
          saveAllBtn.textContent = defaultLabel;
        });
    });
  }
})();
</script>
@endpush
