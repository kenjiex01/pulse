<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div class="min-w-0 flex-1">
        <h1 class="text-xl font-bold tracking-tight text-[#0B318F] sm:text-2xl lg:text-3xl">{{ $title }}</h1>
        @isset($description)
            <p class="mt-1 text-xs text-gray-600 sm:text-sm">{{ $description }}</p>
        @endisset
    </div>
    @php
        $hasTertiary = ! empty($tertiaryActionModalId) || ! empty($tertiaryActionUrl);
        $hasSecondary = ! empty($secondaryActionModalId) || ! empty($secondaryActionUrl);
        $hasPrimary = ! empty($actionModalId) || ! empty($actionUrl);
    @endphp
    @if ($hasTertiary || $hasSecondary || $hasPrimary)
        <div class="flex w-full shrink-0 flex-col gap-2 overflow-visible sm:w-auto sm:flex-row sm:items-center">
            @if ($hasTertiary)
                @if (! empty($tertiaryActionModalId))
                    <button type="button" data-modal-open="{{ $tertiaryActionModalId }}" class="btn-primary relative w-full overflow-visible sm:w-auto">
                        @isset($tertiaryActionIcon)
                            {!! $tertiaryActionIcon !!}
                        @endisset
                        <span>{{ $tertiaryActionLabel ?? 'Sync' }}</span>
                        @isset($tertiaryActionBadgeHtml)
                            {!! $tertiaryActionBadgeHtml !!}
                        @endisset
                    </button>
                @else
                    <a href="{{ $tertiaryActionUrl }}" class="btn-primary w-full sm:w-auto">
                        @isset($tertiaryActionIcon)
                            {!! $tertiaryActionIcon !!}
                        @endisset
                        {{ $tertiaryActionLabel ?? 'Sync' }}
                    </a>
                @endif
            @endif
            @if ($hasSecondary)
                @if (! empty($secondaryActionModalId))
                    <button type="button" data-modal-open="{{ $secondaryActionModalId }}" class="btn-primary w-full sm:w-auto">
                        @isset($secondaryActionIcon)
                            {!! $secondaryActionIcon !!}
                        @endisset
                        {{ $secondaryActionLabel ?? 'Upload' }}
                    </button>
                @else
                    <a href="{{ $secondaryActionUrl }}" class="btn-primary w-full sm:w-auto">
                        @isset($secondaryActionIcon)
                            {!! $secondaryActionIcon !!}
                        @endisset
                        {{ $secondaryActionLabel ?? 'Upload' }}
                    </a>
                @endif
            @endif
            @if (! empty($actionModalId))
                <button type="button" data-modal-open="{{ $actionModalId }}" class="btn-primary w-full sm:w-auto">
                    @isset($actionIcon)
                        {!! $actionIcon !!}
                    @endisset
                    {{ $actionLabel ?? 'Create' }}
                </button>
            @elseif (! empty($actionUrl))
                <a href="{{ $actionUrl }}" class="btn-primary w-full sm:w-auto">
                    @isset($actionIcon)
                        {!! $actionIcon !!}
                    @endisset
                    {{ $actionLabel ?? 'Create' }}
                </a>
            @endif
        </div>
    @endif
</div>
