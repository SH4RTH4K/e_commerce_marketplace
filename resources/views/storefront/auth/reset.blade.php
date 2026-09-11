@extends('layouts.storefront', ['headerVariant' => 'compact'])
@php $title = 'Reset password'; @endphp

@section('content')
<section class="mx-auto max-w-md px-4 py-12">
  <div class="rounded-2xl bg-white p-8 border border-slate-100 shadow-soft">
    <h1 class="font-display text-2xl font-extrabold mb-2">Set a new password</h1>
    <p class="text-sm text-slate-500 mb-6">For <b class="text-ink">{{ $email }}</b></p>

    @if($errors->any())
      <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-xl">
        <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
      @csrf
      <div>
        <label class="block text-sm font-medium mb-1.5">New password</label>
        <input type="password" name="password" required autofocus class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300" />
      </div>
      <div>
        <label class="block text-sm font-medium mb-1.5">Confirm password</label>
        <input type="password" name="password_confirmation" required class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300" />
      </div>
      <button class="btn-shine w-full rounded-full bg-brand-600 text-white font-bold py-3.5 hover:bg-brand-700 transition">Update password</button>
    </form>
  </div>
</section>
@endsection
