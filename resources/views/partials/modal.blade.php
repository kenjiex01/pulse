<div
    id="{{ $id }}"
    class="modal-overlay {{ ($open ?? false) ? '' : 'hidden' }}"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $id }}-title"
    @if ($open ?? false) data-modal-auto-open @endif
>
    <div class="modal-backdrop" data-modal-close aria-hidden="true"></div>
    <div class="modal-panel {{ $panelClass ?? 'max-w-lg' }}">
        <div class="modal-header">
            <div class="flex min-w-0 items-start gap-2">
                @isset($backUrl)
                    <a href="{{ $backUrl }}" class="btn-icon mt-0.5 shrink-0" title="Back" data-no-loader>
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </a>
                @endisset
                <div class="min-w-0">
                    <h2 id="{{ $id }}-title" class="text-lg font-bold text-[#0B318F]">{{ $title }}</h2>
                    @isset($description)
                        <p class="mt-0.5 text-sm text-gray-500">{{ $description }}</p>
                    @endisset
                </div>
            </div>
            <button type="button" class="modal-close-btn" data-modal-close aria-label="Close">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="modal-body">
            {{ $slot ?? '' }}
            @isset($body)
                {!! $body !!}
            @endisset
        </div>
    </div>
</div>
