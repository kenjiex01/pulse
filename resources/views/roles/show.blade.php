@extends('layouts.app')

@section('title', $role->name.' — '.config('app.name'))

@section('content')
    <div class="mb-2">
        <a href="{{ route('roles.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-[#00A3E6] hover:underline">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Bumalik sa Roles
        </a>
    </div>

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-[#0B318F]">{{ $role->name }}</h1>
            <p class="mt-1 text-sm text-gray-600">Detalye ng role</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('roles.edit', $role) }}" class="btn-secondary">I-edit</a>
            @can('delete', $role)
                <form method="POST" action="{{ route('roles.destroy', $role) }}" onsubmit="return confirm('Sigurado ka bang buburahin ang role na ito?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger">Burahin</button>
                </form>
            @endcan
        </div>
    </div>

    <div class="max-w-lg card-panel space-y-4">
        <div>
            <p class="text-sm text-gray-600">Slug</p>
            <p class="mt-1"><span class="badge-brand">{{ $role->slug }}</span></p>
        </div>
        <div>
            <p class="text-sm text-gray-600">Deskripsyon</p>
            <p class="mt-1 font-medium text-gray-900">{{ $role->description ?? '—' }}</p>
        </div>
        <div>
            <p class="text-sm text-gray-600">Bilang ng Users</p>
            <p class="mt-1 font-medium text-gray-900">{{ $role->users_count }}</p>
        </div>
    </div>
@endsection
