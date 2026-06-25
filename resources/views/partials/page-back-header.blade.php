<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex min-w-0 items-start gap-3">
        <a href="{{ $backUrl }}" class="btn-icon mt-0.5 shrink-0" title="Back">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div class="min-w-0">
            <h1 class="text-xl font-bold tracking-tight text-[#0B318F] sm:text-2xl">{{ $title }}</h1>
            @isset($description)
                <p class="mt-1 text-xs text-gray-600 sm:text-sm">{{ $description }}</p>
            @endisset
        </div>
    </div>
    @isset($actionUrl)
        <a href="{{ $actionUrl }}" class="{{ $actionClass ?? 'btn-primary' }} w-full shrink-0 sm:w-auto">
            {{ $actionLabel }}
        </a>
    @endisset
</div>
