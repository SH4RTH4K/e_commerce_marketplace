<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>@yield('title', 'Admin') &mdash; {{ site_name() }} Admin</title>
  <meta name="robots" content="noindex, nofollow" />
  <link rel="icon" href="{{ favicon_url() }}" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = { theme: { extend: {
      colors: { primary: { DEFAULT: '#f15a24' } },
      fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui'] },
    } } };
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="{{ asset('theme/css/admin.css') . '?v=chat1' }}" />
  <style>body{font-family:'Inter',sans-serif}</style>
</head>
<body class="bg-gray-100 text-gray-800 antialiased{{ testing_mode() ? ' testing-mode' : '' }}">
  @if(testing_mode())
    <div class="bg-amber-50 border-b border-amber-200 text-amber-900 text-sm px-4 py-2 text-center font-medium">
      Testing mode is on &mdash; Save, Delete, and other write actions are disabled.
    </div>
  @endif
  <div class="admin-shell flex min-h-screen min-h-[100dvh]">
    @include('admin.partials.sidebar')

    <div class="admin-main flex-1 flex flex-col min-w-0 w-full">
      @include('admin.partials.topbar')

      @if(session('status'))
        <div class="px-3 sm:px-4 lg:px-6 pt-4">
          <div class="bg-primary/10 border border-primary/20 text-primary text-sm font-medium px-4 py-3 rounded-xl">{{ session('status') }}</div>
        </div>
      @endif
      @if($errors->any())
        <div class="px-3 sm:px-4 lg:px-6 pt-4">
          <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-xl">
            <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
          </div>
        </div>
      @endif

      <main class="flex-1 p-3 sm:p-4 lg:p-6">
        @yield('content')
      </main>
    </div>
  </div>
  <div id="backdrop" class="fixed inset-0 bg-ink/40 z-40 hidden lg:hidden" aria-hidden="true"></div>
  <script src="{{ asset('theme/js/admin-shell.js') }}"></script>
  <script src="{{ asset('theme/js/admin-bulk.js') }}?v=bulk2"></script>
  @if(testing_mode())
  <script>
    (function () {
      var MSG = 'Aita testing mode ase, tumi purchase korar por aigula delete edit shob kisu korte parbe.';

      function formMethod(form) {
        var method = (form.getAttribute('method') || 'get').toUpperCase();
        var spoof = form.querySelector('input[name="_method"]');
        if (spoof && spoof.value) method = spoof.value.toUpperCase();
        return method;
      }

      function isLogoutForm(form) {
        var action = (form.getAttribute('action') || '').toLowerCase();
        return action.indexOf('/admin/logout') !== -1;
      }

      function isMutatingForm(form) {
        if (!(form instanceof HTMLFormElement)) return false;
        if (isLogoutForm(form)) return false;
        return formMethod(form) !== 'GET';
      }

      function markLockedControls() {
        document.querySelectorAll('form').forEach(function (form) {
          if (!isMutatingForm(form)) return;
          form.querySelectorAll('button, input[type="submit"], input[type="button"]').forEach(function (el) {
            el.classList.add('testing-locked');
          });
        });
        document.querySelectorAll('button[form], input[type="submit"][form]').forEach(function (el) {
          var form = document.getElementById(el.getAttribute('form'));
          if (isMutatingForm(form)) el.classList.add('testing-locked');
        });
        var saveAll = document.getElementById('saveAllSettings');
        if (saveAll) saveAll.classList.add('testing-locked');
      }

      document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!isMutatingForm(form)) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        alert(MSG);
      }, true);

      document.addEventListener('click', function (e) {
        var saveAll = e.target.closest('#saveAllSettings');
        if (saveAll) {
          e.preventDefault();
          e.stopImmediatePropagation();
          alert(MSG);
          return;
        }

        var btn = e.target.closest('button[form], input[type="submit"][form]');
        if (!btn || !btn.getAttribute('form')) return;
        var form = document.getElementById(btn.getAttribute('form'));
        if (!isMutatingForm(form)) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        alert(MSG);
      }, true);

      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', markLockedControls);
      } else {
        markLockedControls();
      }
    })();
  </script>
  @endif
  <script src="{{ asset('theme/js/upload-fallback.js') }}?v=tmp1"></script>
  @stack('scripts')
</body>
</html>
