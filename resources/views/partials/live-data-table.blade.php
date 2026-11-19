@php
    $searchId = $searchId ?? 'live-table-search-'.md5($url);
    $currentPerPage = $paginator?->perPage() ?? 10;
    $live = $live ?? true;
@endphp

<div
    class="datatable-skolaris"
    @if ($live)
        data-live-table
        data-live-table-url="{{ $url }}"
        data-live-table-debounce="{{ $debounce ?? 300 }}"
    @else
        data-client-table
    @endif
>
    <div @class([
        'mb-4 flex flex-col gap-3',
        'sm:justify-end' => empty($filters),
    ])>
        @if (! empty($filters))
            <div @class([
                'flex flex-col gap-3' => ! ($filtersInline ?? false),
                'datatable-skolaris-toolbar-inline' => $filtersInline ?? false,
            ])>
                <div @class(['min-w-0' => ! ($filtersInline ?? false)]) data-live-table-filters>
                    {!! $filters !!}
                </div>

                @if ($showSearch ?? true)
                    <div @class([
                        'flex justify-end' => ! ($filtersInline ?? false),
                        'datatable-skolaris-toolbar-search' => $filtersInline ?? false,
                        'w-full sm:w-72' => ! ($filtersInline ?? false),
                    ])>
                        <div class="w-full">
                            <label for="{{ $searchId }}" class="form-label">Search</label>
                            <input
                                id="{{ $searchId }}"
                                type="search"
                                value="{{ $search ?? '' }}"
                                placeholder="{{ $searchPlaceholder ?? 'Search...' }}"
                                class="form-input w-full"
                                @if ($live) data-live-table-search @else data-client-table-search @endif
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
                    @if ($live) data-live-table-search @else data-client-table-search @endif
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
                    @if ($live) data-live-table-per-page @else data-client-table-per-page @endif
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
