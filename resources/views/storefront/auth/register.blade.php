@extends('layouts.storefront')
@php $title = 'Login / Register'; @endphp

@section('content')
<style>
  .fld { width: 100%; border: 1px solid #d1d5db; border-radius: .375rem; padding: .625rem .75rem; font-size: .875rem; }
  .fld:focus { outline: none; box-shadow: 0 0 0 2px rgba(241, 90, 36, .3); }
</style>
<main class="max-w-md mx-auto px-5 py-12">
  <div class="bg-white rounded-md overflow-hidden" data-tabs>
    <div class="grid grid-cols-2 text-center font-semibold">
      <a href="{{ route('login') }}" class="py-4 border-b-2 border-transparent text-gray-500">Login</a>
      <button type="button" class="tab-active py-4 border-b-2">Register</button>
    </div>

    <form method="POST" action="{{ route('register.store') }}" class="p-6 space-y-4">
      @csrf
      @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-3 py-2 rounded">
          <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
      @endif
      <div>
        <label class="block text-sm text-gray-600 mb-1">Full name</label>
        <input name="name" value="{{ old('name') }}" class="fld" placeholder="Your name" required />
      </div>
      <div>
        <label class="block text-sm text-gray-600 mb-1">Phone</label>
        <input name="phone" value="{{ old('phone') }}" class="fld" placeholder="01XXXXXXXXX" />
      </div>
      <div>
        <label class="block text-sm text-gray-600 mb-1">Email</label>
        <input type="email" name="email" value="{{ old('email') }}" class="fld" placeholder="you@example.com" required />
      </div>
      <div>
        <label class="block text-sm text-gray-600 mb-1">Password</label>
        <input type="password" name="password" class="fld" placeholder="••••••••" required />
      </div>
      <div>
        <label class="block text-sm text-gray-600 mb-1">Confirm password</label>
        <input type="password" name="password_confirmation" class="fld" placeholder="••••••••" required />
      </div>
      <button type="submit" class="block w-full text-center bg-brand-600 hover:bg-brand-700 text-white font-semibold py-3 rounded-md">Create account</button>
    </form>
  </div>
</main>
@endsection
