@php
    $isEdit = $isEdit ?? false;
    $submitLabel = $isEdit ? 'Save Changes' : 'Create Employee';
@endphp

<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div class="min-w-0">
        <h1 class="text-xl font-bold tracking-tight text-[#0B318F] sm:text-2xl lg:text-3xl">{{ $title }}</h1>
        @isset($description)
            <p class="mt-1 text-xs text-gray-600 sm:text-sm">{{ $description }}</p>
        @endisset
    </div>
    <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
        <a href="{{ $backUrl }}" class="btn-secondary w-full sm:w-auto">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Back
        </a>
        <button type="submit" form="employee-form" class="btn-primary w-full sm:w-auto">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
            {{ $submitLabel }}
        </button>
    </div>
</div>
