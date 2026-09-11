@extends('layouts.storefront', ['headerVariant' => 'compact'])
@php $title = 'Verify email'; @endphp

@section('content')
<section class="mx-auto max-w-md px-4 py-12">
  <div class="rounded-2xl bg-white p-8 border border-slate-100 shadow-soft text-center">
    <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-brand-50 text-brand-600 mb-4">
      <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/></svg>
    </div>
    <h1 class="font-display text-2xl font-extrabold">Check your email</h1>
    <p class="mt-2 text-sm text-slate-500">We sent a 6-digit code to <b class="text-ink">{{ $email }}</b>. Enter it below.</p>

    @if(session('dev_otp'))
      <div class="mt-4 bg-amber-50 border border-amber-200 text-amber-800 text-sm px-4 py-2.5 rounded-xl">Dev mode: your code is <b class="tracking-widest">{{ session('dev_otp') }}</b></div>
    @endif
    @if($errors->any())
      <div class="mt-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-2.5 rounded-xl">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('verify.store') }}" class="mt-6 space-y-4">
      @csrf
      <input name="code" inputmode="numeric" maxlength="6" required autofocus placeholder="••••••" class="w-full text-center tracking-[0.5em] text-2xl font-bold rounded-xl border border-slate-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-brand-300" />
      <button class="btn-shine w-full rounded-full bg-brand-600 text-white font-bold py-3.5 hover:bg-brand-700 transition">Verify</button>
    </form>

    <form method="POST" action="{{ route('verify.resend') }}" class="mt-4">
      @csrf
      <button class="text-sm text-brand-600 font-semibold hover:underline">Didn't get it? Resend code</button>
    </form>
  </div>
</section>
@endsection
