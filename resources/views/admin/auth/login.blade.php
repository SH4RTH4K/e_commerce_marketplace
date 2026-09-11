<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Login — {{ site_name() }}</title>
  <meta name="robots" content="noindex, nofollow" />
  <link rel="icon" href="{{ favicon_url() }}" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = { theme: { extend: {
      colors: { primary: { DEFAULT: '#f15a24' }, brand: { 600: '#f15a24' } },
      fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui'] },
    } } };
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
  <div class="w-full max-w-md">
    <div class="flex items-center justify-center gap-2.5 mb-6">
      @php $loginSite = site_name(); @endphp
      @if(has_custom_logo())
        <img src="{{ logo_url() }}" alt="{{ $loginSite }}" class="h-10 w-10 rounded-lg object-contain" width="40" height="40" />
      @else
        <span class="grid h-10 w-10 place-items-center rounded-lg bg-brand-500 text-white shrink-0">
          <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M5 8h14l-1.4 9H6.4z"/><path d="M8 8a4 4 0 0 1 8 0" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
        </span>
      @endif
      <span class="font-display font-extrabold text-2xl tracking-tight" style="color:#f15a24">{{ $loginSite }}</span>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-8">
      <h1 class="text-xl font-bold text-center">Admin Sign in</h1>
      <p class="text-sm text-gray-500 text-center mt-1 mb-6">Welcome back, please sign in to continue.</p>

      @if($errors->any())
        <div class="mb-4 bg-red-50 border border-red-200 text-red-600 text-sm px-3 py-2 rounded-lg">{{ $errors->first() }}</div>
      @endif



      <form method="POST" action="{{ route('admin.login.attempt') }}" class="space-y-4">
        @csrf
        <div>
          <label class="block text-sm text-gray-600 mb-1">Username</label>
          <input type="text" name="username" value="{{ old('username') }}" required autofocus autocomplete="username"
            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40" />
        </div>
        <div>
          <div class="flex items-center justify-between mb-1">
            <label class="text-sm text-gray-600">Password</label>
          </div>
          <input type="password" name="password" value="" required autocomplete="current-password"
            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40" />
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-600">
          <input type="checkbox" name="remember" class="rounded border-gray-300 text-primary" /> Remember me
        </label>
        <button type="submit" class="w-full bg-primary hover:bg-primary/90 text-white font-medium rounded-lg py-2.5">Sign in</button>
      </form>
    </div>

    <p class="text-center text-xs text-gray-400 mt-6">© {{ date('Y') }} {{ site_name() }}. Admin Panel.</p>
    <p class="text-center mt-2"><a href="{{ route('home') }}" class="text-sm text-gray-500 hover:text-primary">← Back to store</a></p>
  </div>
</body>
</html>
