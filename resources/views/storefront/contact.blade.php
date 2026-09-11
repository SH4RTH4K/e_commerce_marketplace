@extends('layouts.storefront')

@php
  $title = $title ?? 'Contact';
  $inputClass = 'w-full border border-gray-300 rounded-md px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-600/30 bg-white';
@endphp

@section('content')
  <main class="max-w-7xl mx-auto px-4 py-6">
    <nav class="text-sm text-gray-500 mb-4">
      <a href="{{ route('home') }}" class="hover:text-brand-600">Home</a> / Contact
    </nav>

    @if(session('status'))
      <div class="mb-6 rounded-md bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm px-4 py-3">{{ session('status') }}</div>
    @endif
    @if($errors->any())
      <div class="mb-6 rounded-md bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">
        <p class="font-bold">Please fix the following:</p>
        <ul class="list-disc list-inside mt-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    <div class="grid lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 bg-white border border-gray-200 rounded-md p-6">
        <h1 class="text-2xl font-extrabold mb-1">{{ setting('contact_title', 'Get in touch') }}</h1>
        <p class="text-gray-500 mb-5">{{ setting('contact_intro', 'We usually reply within a few hours.') }}</p>
        @include('storefront.partials.contact-form-fields', ['inputClass' => $inputClass, 'buttonClass' => 'bg-brand-600 hover:bg-brand-700 text-white font-semibold px-6 py-3 rounded-md'])
      </div>
      <div class="space-y-4">
        @if(($address ?? '') !== '' || setting('contact_address'))
          <div class="bg-white border border-gray-200 rounded-md p-6">
            <h3 class="font-semibold mb-2">Address</h3>
            <p class="text-sm text-gray-600">{{ $address !== '' ? $address : setting('contact_address') }}</p>
          </div>
        @endif
        @if(($phone ?? '') !== '' || setting('contact_phone'))
          @php $p = $phone !== '' ? $phone : setting('contact_phone'); @endphp
          <div class="bg-white border border-gray-200 rounded-md p-6">
            <h3 class="font-semibold mb-2">Phone</h3>
            <p class="text-sm text-gray-600"><a href="tel:{{ preg_replace('/\s+/', '', (string) $p) }}" class="hover:text-brand-600">{{ $p }}</a></p>
          </div>
        @endif
        @if(($email ?? '') !== '' || setting('contact_email'))
          @php $em = $email !== '' ? $email : setting('contact_email'); @endphp
          <div class="bg-white border border-gray-200 rounded-md p-6">
            <h3 class="font-semibold mb-2">Email</h3>
            <p class="text-sm text-gray-600"><a href="mailto:{{ $em }}" class="hover:text-brand-600">{{ $em }}</a></p>
          </div>
        @endif
        @if(($hours ?? '') !== '' || trim((string) setting('contact_hours', '')) !== '')
          <div class="bg-white border border-gray-200 rounded-md p-6">
            <h3 class="font-semibold mb-2">Hours</h3>
            <p class="text-sm text-gray-600">{{ $hours !== '' ? $hours : setting('contact_hours') }}</p>
          </div>
        @endif
      </div>
    </div>
  </main>
@endsection