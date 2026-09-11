@extends('layouts.admin')
@section('title', 'Change Password')

@section('content')
<form method="POST" action="{{ route('admin.admins.password.update') }}" class="max-w-xl space-y-6">
  @csrf
  @method('PUT')

  <div class="flex items-center justify-between">
    <div>
      <a href="{{ route('admin.admins.index') }}" class="text-sm text-gray-500 hover:text-primary">&larr; Back to admins</a>
      <h2 class="text-xl font-bold mt-1">Change my password</h2>
      <p class="text-sm text-gray-500 mt-1">Signed in as {{ auth()->user()->email }}</p>
    </div>
    <button class="btn-primary">Update password</button>
  </div>

  <div class="card p-5 space-y-4">
    <div>
      <label class="lbl">Current password</label>
      <input type="password" name="current_password" class="inp" required autocomplete="current-password" />
      @error('current_password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
      <label class="lbl">New password</label>
      <input type="password" name="password" class="inp" required autocomplete="new-password" />
      @error('password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
      <label class="lbl">Confirm new password</label>
      <input type="password" name="password_confirmation" class="inp" required autocomplete="new-password" />
    </div>
  </div>
</form>
@endsection
