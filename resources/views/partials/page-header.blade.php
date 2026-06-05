<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div class="min-w-0 flex-1">
        <h1 class="text-xl font-bold tracking-tight text-[#0B318F] sm:text-2xl lg:text-3xl">{{ $title }}</h1>
        @isset($description)
            <p class="mt-1 text-xs text-gray-600 sm:text-sm">{{ $description }}</p>
        @endisset
    </div>
    @isset($actionUrl)
        <a href="{{ $actionUrl }}" class="btn-primary w-full shrink-0 sm:w-auto">
            @isset($actionIcon)
                {!! $actionIcon !!}
            @endisset
            {{ $actionLabel ?? 'Create' }}
        </a>
    @endisset
</div>
