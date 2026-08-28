@php
    $searchId = $searchId ?? 'live-table-search-'.md5($url);
    $currentPerPage = $paginator?->perPage() ?? 10;
@endphp

<div
    class="datatable-skolaris"
    data-live-table
    data-live-table-url="{{ $url }}"
    data-live-table-debounce="{{ $debounce ?? 300 }}"
>
    <div @class([
        'mb-4 flex flex-col gap-3',
        'sm:justify-end' => empty($filters),
    ])>
        @if (! empty($filters))
            <div class="flex flex-col gap-3">
                <div class="min-w-0" data-live-table-filters>
                    {!! $filters !!}
                </div>

                @if ($showSearch ?? true)
                    <div class="flex justify-end">
                        <div class="w-full sm:w-72">
                            <label for="{{ $searchId }}" class="form-label">Search</label>
                            <input
                                id="{{ $searchId }}"
                                type="search"
                                value="{{ $search ?? '' }}"
                                placeholder="{{ $searchPlaceholder ?? 'Search...' }}"
                                class="form-input w-full"
                                data-live-table-search
                                autocomplete="off"
                            >
                        </div>
                    </div>
                @endif
            </div>
        @elseif ($showSearch ?? true)
            <div class="flex justify-end">
                <label for="{{ $searchId }}" class="sr-only">{{ $searchPlaceholder ?? 'Search' }}</label>
                <input
                    id="{{ $searchId }}"
                    type="search"
                    value="{{ $search ?? '' }}"
                    placeholder="{{ $searchPlaceholder ?? 'Search...' }}"
                    class="form-input w-full max-w-xs"
                    data-live-table-search
                    autocomplete="off"
                >
            </div>
        @endif
    </div>

    @if ($paginator)
        <div class="datatable-skolaris-meta mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-gray-600" data-live-table-total data-total-label="{{ $totalLabel ?? 'records' }}">
                Total: {{ number_format($paginator->total()) }} {{ $totalLabel ?? 'records' }}
            </div>
            <div class="flex items-center gap-2">
                <label for="{{ $searchId }}-per-page" class="text-sm text-gray-600">Show:</label>
                <select
                    id="{{ $searchId }}-per-page"
                    class="datatable-skolaris-per-page"
                    data-live-table-per-page
                >
                    @foreach (\App\Support\LiveTable::PER_PAGE_OPTIONS as $size)
                        <option value="{{ $size }}" @selected($currentPerPage === $size)>{{ $size }} per page</option>
                    @endforeach
                </select>
            </div>
        </div>
    @endif

    <div class="relative">
        @include('partials.live-data-table-loading')

        <div data-live-table-results>
            {!! $results !!}
        </div>
    </div>
</div>
