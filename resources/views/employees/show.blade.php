@extends('layouts.app')

@section('title', $employee->full_name.' — '.config('app.name'))

@section('content')
    @include('partials.flash')

    @include('partials.page-back-header', array_filter([
        'backUrl' => route('employees.index'),
        'title' => 'Employee Details',
        'description' => $employee->employee_number,
        'actionUrl' => auth()->user()->can('update', $employee) ? route('employees.edit', $employee) : null,
        'actionLabel' => 'Edit Employee',
    ]))

    @include('employees._show-sections', ['employee' => $employee])

    <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-between">
        <a href="{{ route('employees.index') }}" class="btn-secondary w-full sm:w-auto">Back to List</a>
        <div class="flex flex-col gap-2 sm:flex-row">
            @can('update', $employee)
                <a href="{{ route('employees.edit', $employee) }}" class="btn-secondary w-full sm:w-auto">Edit</a>
            @endcan
            @can('delete', $employee)
                <form method="POST" action="{{ route('employees.destroy', $employee) }}" onsubmit="return confirm('Are you sure you want to delete this employee?')" class="w-full sm:w-auto">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger w-full">Delete</button>
                </form>
            @endcan
        </div>
    </div>
@endsection
