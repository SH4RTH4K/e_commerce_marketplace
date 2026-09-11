@extends('layouts.admin')
@php $editing = $admin->exists; @endphp
@section('title', $editing ? 'Edit Admin' : 'New Admin')

@section('content')
<form method="POST" action="{{ $editing ? route('admin.admins.update', $admin) : route('admin.admins.store') }}" class="max-w-xl space-y-6">
  @csrf
  @if($editing) @method('PUT') @endif

  <div class="flex items-center justify-between">
    <div>
      <a href="{{ route('admin.admins.index') }}" class="text-sm text-gray-500 hover:text-primary">&larr; Back to admins</a>
      <h2 class="text-xl font-bold mt-1">{{ $editing ? 'Edit admin' : 'Add admin' }}</h2>
    </div>
    <button class="btn-primary">{{ $editing ? 'Save changes' : 'Create admin' }}</button>
  </div>

  <div class="card p-5 space-y-4">
    <div>
      <label class="lbl">Name</label>
      <input name="name" class="inp" value="{{ old('name', $admin->name) }}" required />
      @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
      <label class="lbl">Email</label>
      <input type="email" name="email" class="inp" value="{{ old('email', $admin->email) }}" required />
      @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
      <label class="lbl">{{ $editing ? 'New password (leave blank to keep)' : 'Password' }}</label>
      <input type="password" name="password" class="inp" autocomplete="new-password" @required(! $editing) />
      @error('password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
      <label class="lbl">Confirm password</label>
      <input type="password" name="password_confirmation" class="inp" autocomplete="new-password" @required(! $editing) />
    </div>
  </div>
</form>
@endsection
