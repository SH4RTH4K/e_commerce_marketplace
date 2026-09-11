@extends('layouts.storefront', ['headerVariant' => 'compact'])
@php $title = 'Forgot password'; @endphp

@section('content')
<section class="mx-auto max-w-md px-4 py-12">
  <div class="rounded-2xl bg-white p-8 border border-slate-100 shadow-soft">
    <h1 class="font-display text-2xl font-extrabold mb-2">Forgot password</h1>
    <p class="text-sm text-slate-500 mb-6">Enter your email and we'll send a reset code.</p>

    @if($errors->any())
      <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-xl">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
      @csrf
      <div>
        <label class="block text-sm font-medium mb-1.5">Email</label>
        <input type="email" name="email" value="{{ old('email') }}" required autofocus class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300" />
      </div>
      <button class="btn-shine w-full rounded-full bg-brand-600 text-white font-bold py-3.5 hover:bg-brand-700 transition">Send reset code</button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500"><a href="{{ route('login') }}" class="text-brand-600 font-semibold hover:underline">Back to sign in</a></p>
  </div>
</section>
@endsection
