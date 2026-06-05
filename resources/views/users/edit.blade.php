@extends('layouts.app')

@section('title', 'I-edit ang User — '.config('app.name'))

@section('content')
    <div class="mb-2">
        <a href="{{ route('users.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-[#00A3E6] hover:underline">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Bumalik sa Users
        </a>
    </div>

    @include('partials.page-header', ['title' => 'I-edit ang User', 'description' => 'I-update ang impormasyon ng user'])

    <div class="max-w-lg card-panel">
        <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-5">
            @csrf
            @method('PUT')
            @include('users._form', ['user' => $user, 'roles' => $roles])
            <button type="submit" class="btn-primary">I-update</button>
        </form>
    </div>
@endsection
