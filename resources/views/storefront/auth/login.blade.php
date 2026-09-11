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
      <button type="button" data-tab="in" class="tab-active py-4 border-b-2">Login</button>
      <a href="{{ route('register') }}" data-tab="up" class="py-4 border-b-2 border-transparent text-gray-500">Register</a>
    </div>

    <form method="POST" action="{{ route('login.store') }}" data-pane="in" data-panel="in" class="p-6 space-y-4">
      @csrf
      @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-3 py-2 rounded">{{ $errors->first() }}</div>
      @endif



      <div>
        <label class="block text-sm text-gray-600 mb-1">Email or phone</label>
        <input type="email" name="email" value="{{ old('email') }}" class="fld" placeholder="you@example.com" required autofocus />
      </div>
      <div>
        <div class="flex justify-between mb-1">
          <label class="text-sm text-gray-600">Password</label>
          <a href="{{ route('password.request') }}" class="text-xs text-brand-600">Forgot?</a>
        </div>
        <input type="password" name="password" value="" class="fld" placeholder="••••••••" required />
      </div>
      <label class="flex items-center gap-2 text-sm text-gray-600">
        <input type="checkbox" name="remember" class="rounded border-gray-300 text-brand-600" /> Remember me
      </label>
      <button type="submit" class="block w-full text-center bg-brand-600 hover:bg-brand-700 text-white font-semibold py-3 rounded-md">Login</button>
    </form>
  </div>
</main>
@endsection
