@php
    $isSelected = (string) ($selectedCampusId ?? '') === (string) $campus->campus_id;
@endphp

<label class="campus-card {{ $isSelected ? 'campus-card-selected' : '' }}">
    <input
        type="radio"
        name="campus_id"
        value="{{ $campus->campus_id }}"
        class="sr-only"
        @checked($isSelected)
    >
    @if ($isSelected)
        <div class="absolute top-3 right-3 z-10">
            <div class="rounded-full bg-[#00A3E6] p-1.5">
                <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </div>
        </div>
    @endif

    <div class="campus-card-image">
        <svg class="h-16 w-16 text-white/50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
    </div>

    <div class="p-6">
        <h3 class="mb-1 text-xl font-bold text-gray-900">{{ $campus->campus_name }}</h3>
        @if ($campus->campus_code)
            <p class="mb-3 text-sm font-medium text-gray-500">{{ $campus->campus_code }}</p>
        @endif

        <div class="space-y-2 text-sm text-gray-600">
            @if ($campus->address)
                <div class="flex items-start gap-2">
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span class="line-clamp-2">{{ $campus->address }}</span>
                </div>
            @endif
            @if ($campus->phone)
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    <span>{{ $campus->phone }}</span>
                </div>
            @endif
            @if ($campus->email)
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <span>{{ $campus->email }}</span>
                </div>
            @endif
            @if ($campus->website)
                <div class="flex items-center gap-2 text-[#00A3E6]">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                    <a href="{{ $campus->website }}" target="_blank" rel="noopener noreferrer" class="hover:underline" onclick="event.stopPropagation()">Visit Website</a>
                </div>
            @endif
        </div>

        @if ($isSelected)
            <div class="mt-4 border-t border-blue-200 pt-4">
                <span class="inline-flex items-center rounded-full bg-[#00A3E6] px-3 py-1 text-xs font-semibold text-white">Selected</span>
            </div>
        @endif
    </div>
</label>
