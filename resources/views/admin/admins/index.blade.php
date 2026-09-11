@extends('layouts.admin')
@section('title', 'Admins')

@section('content')
<div class="space-y-6">
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <div>
      <h2 class="text-xl font-bold">Admins</h2>
      <p class="text-sm text-gray-500 mt-1">Create admin accounts and change passwords.</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <a href="{{ route('admin.admins.password.edit') }}" class="px-4 py-2 text-sm rounded-lg border border-gray-300 bg-white hover:bg-gray-50">Change my password</a>
      <a href="{{ route('admin.admins.create') }}" class="btn-primary">+ Add admin</a>
    </div>
  </div>

  @if($errors->has('admin'))
    <div class="rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">{{ $errors->first('admin') }}</div>
  @endif

  <div class="card overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="text-left text-gray-500 bg-gray-50">
        <tr>
          <th class="px-5 py-3 font-medium">Name</th>
          <th class="px-5 py-3 font-medium">Email</th>
          <th class="px-5 py-3 font-medium">Created</th>
          <th class="px-5 py-3 font-medium text-right">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        @forelse($admins as $admin)
          <tr class="hover:bg-gray-50">
            <td class="px-5 py-3 font-medium">
              {{ $admin->name }}
              @if((int) $admin->id === (int) auth()->id())
                <span class="ml-1 text-xs text-primary">(you)</span>
              @endif
            </td>
            <td class="px-5 py-3 text-gray-500">{{ $admin->email }}</td>
            <td class="px-5 py-3 text-gray-500">{{ $admin->created_at?->format('d M Y') }}</td>
            <td class="px-5 py-3 text-right whitespace-nowrap">
              <a href="{{ route('admin.admins.edit', $admin) }}" class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-600">Edit</a>
              @if((int) $admin->id !== (int) auth()->id())
                <form method="POST" action="{{ route('admin.admins.destroy', $admin) }}" class="inline" onsubmit="return confirm('Delete admin {{ $admin->email }}?')">
                  @csrf @method('DELETE')
                  <button class="px-2 py-1 text-xs rounded bg-red-50 text-red-600 border border-red-200">Delete</button>
                </form>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="px-5 py-10 text-center text-gray-400">No admin accounts found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
