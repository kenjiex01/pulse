@extends('layouts.app')

@section('title', 'Bagong User — '.config('app.name'))

@section('content')
    <div class="mb-2">
        <a href="{{ route('users.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-[#00A3E6] hover:underline">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Bumalik sa Users
        </a>
    </div>

    @include('partials.page-header', ['title' => 'Bagong User', 'description' => 'Gumawa ng bagong user account'])

    <div class="max-w-lg card-panel">
        <form method="POST" action="{{ route('users.store') }}" class="space-y-5">
            @csrf
            @include('users._form', ['roles' => $roles])
            <button type="submit" class="btn-primary">I-save</button>
        </form>
    </div>
@endsection
