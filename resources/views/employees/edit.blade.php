@extends('layouts.app')

@section('title', 'Edit '.$employee->full_name.' — '.config('app.name'))

@section('content')
    @include('partials.flash')

    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            <p class="font-semibold">Could not save — please fix the following:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @include('employees._form', ['employee' => $employee, 'campuses' => $campuses])
@endsection
