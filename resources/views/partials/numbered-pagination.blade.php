@php
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator */
    $windowSize = 5;
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $windowStart = max(1, $current - (int) floor($windowSize / 2));
    $windowEnd = min($last, $windowStart + $windowSize - 1);
    $windowStart = max(1, $windowEnd - $windowSize + 1);
@endphp

@if ($paginator->total() > 0)
    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-gray-500">
            Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}
        </p>

        @if ($paginator->hasPages())
            <div class="flex items-center gap-1">
                @if ($paginator->onFirstPage())
                    <span class="btn-secondary !px-3 !py-1.5 text-xs opacity-40" aria-disabled="true">Previous</span>
                @else
                    <a
                        href="{{ $paginator->previousPageUrl() }}"
                        class="btn-secondary !px-3 !py-1.5 text-xs"
                        data-live-table-page
                    >Previous</a>
                @endif

                @if ($windowStart > 1)
                    <span class="px-1.5 text-xs text-gray-400">…</span>
                @endif

                @for ($page = $windowStart; $page <= $windowEnd; $page++)
                    @if ($page === $current)
                        <span class="min-w-[1.9rem] rounded-md bg-[#0B318F] px-2 py-1.5 text-center text-xs font-medium text-white">{{ $page }}</span>
                    @else
                        <a
                            href="{{ $paginator->url($page) }}"
                            class="min-w-[1.9rem] rounded-md border border-gray-300 px-2 py-1.5 text-center text-xs text-gray-700 hover:bg-gray-50"
                            data-live-table-page
                        >{{ $page }}</a>
                    @endif
                @endfor

                @if ($windowEnd < $last)
                    <span class="px-1.5 text-xs text-gray-400">…</span>
                @endif

                @if ($paginator->hasMorePages())
                    <a
                        href="{{ $paginator->nextPageUrl() }}"
                        class="btn-secondary !px-3 !py-1.5 text-xs"
                        data-live-table-page
                    >Next</a>
                @else
                    <span class="btn-secondary !px-3 !py-1.5 text-xs opacity-40" aria-disabled="true">Next</span>
                @endif
            </div>
        @endif
    </div>
@endif
