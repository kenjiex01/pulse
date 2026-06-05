@extends('layouts.app')

@section('title', $user->name.' — '.config('app.name'))

@section('content')
    <div class="mb-2">
        <a href="{{ route('users.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-[#00A3E6] hover:underline">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Back to Users
        </a>
    </div>

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-[#0B318F]">{{ $user->name }}</h1>
            <p class="mt-1 text-sm text-gray-600">User account details</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('users.edit', $user) }}" class="btn-secondary">Edit</a>
            @can('delete', $user)
                <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Are you sure you want to delete this user?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger">Delete</button>
                </form>
            @endcan
        </div>
    </div>

    <div class="max-w-lg card-panel space-y-4">
        <div>
            <p class="text-sm text-gray-600">Email</p>
            <p class="mt-1 font-medium text-gray-900">{{ $user->email }}</p>
        </div>
        <div>
            <p class="text-sm text-gray-600">Role</p>
            <p class="mt-1">
                @if ($user->role)
                    <span class="badge-brand">{{ $user->role->name }}</span>
                @else
                    —
                @endif
            </p>
        </div>
        <div>
            <p class="text-sm text-gray-600">Created at</p>
            <p class="mt-1 font-medium text-gray-900">{{ $user->created_at?->format('M d, Y h:i A') }}</p>
        </div>
    </div>
@endsection
