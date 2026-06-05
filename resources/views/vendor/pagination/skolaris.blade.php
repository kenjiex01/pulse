@if ($paginator->hasPages())
    <div class="pagination-skolaris">
        <div class="pagination-skolaris-info">
            @if ($paginator->firstItem())
                Ipakita ang {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} sa {{ $paginator->total() }} resulta
            @else
                Walang resulta
            @endif
        </div>

        <div class="pagination-skolaris-nav">
            {{-- First --}}
            @if ($paginator->onFirstPage())
                <span class="pagination-skolaris-btn opacity-40" aria-disabled="true">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/></svg>
                </span>
            @else
                <a href="{{ $paginator->url(1) }}" class="pagination-skolaris-btn" aria-label="Unang pahina">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/></svg>
                </a>
            @endif

            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <span class="pagination-skolaris-btn opacity-40" aria-disabled="true">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="pagination-skolaris-btn" aria-label="Nakaraan">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </a>
            @endif

            <span class="pagination-skolaris-page">
                Pahina {{ $paginator->currentPage() }} sa {{ $paginator->lastPage() }}
            </span>

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="pagination-skolaris-btn" aria-label="Susunod">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            @else
                <span class="pagination-skolaris-btn opacity-40" aria-disabled="true">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </span>
            @endif

            {{-- Last --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->url($paginator->lastPage()) }}" class="pagination-skolaris-btn" aria-label="Huling pahina">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
                </a>
            @else
                <span class="pagination-skolaris-btn opacity-40" aria-disabled="true">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
                </span>
            @endif
        </div>
    </div>
@endif
