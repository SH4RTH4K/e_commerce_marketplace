@extends('layouts.storefront', ['headerVariant' => 'compact'])

@section('content')
  <section class="bg-white border-b border-slate-100">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-8">
      <nav class="text-sm text-slate-500">
        <a href="{{ route('home') }}" class="hover:text-brand-600">Home</a>
        <span class="mx-2 text-slate-300">/</span>
        <span class="text-ink font-medium">{{ $heading }}</span>
      </nav>
      <h1 class="mt-3 font-display text-3xl font-extrabold text-ink">{{ $heading }}</h1>
    </div>
  </section>
  <section class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-10">
    <div class="rounded-2xl bg-white border border-slate-100 p-6 sm:p-8 text-slate-600 leading-relaxed space-y-4">
      @foreach(preg_split('/\n\n+/', (string) $body) as $para)
        @if(trim($para))<p>{{ $para }}</p>@endif
      @endforeach
    </div>
  </section>
@endsection
