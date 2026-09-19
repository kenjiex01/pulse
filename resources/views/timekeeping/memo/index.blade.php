@extends('layouts.app')

@section('title', 'Memo — '.config('app.name'))

@section('content')
    @include('partials.flash')

    @include('partials.page-header', [
        'title' => 'Memo',
        'description' => 'Find employees with late, undertime, or absent violations and send memos from configured templates.',
    ])

    <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
        <form method="GET" action="{{ route(\App\Support\TimekeepingMemo::routeName('index')) }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div>
                <label for="date_from" class="form-label">Date from</label>
                <input type="date" id="date_from" name="date_from" value="{{ $memoFilters['date_from'] ?? '' }}" class="form-input w-full" required>
            </div>
            <div>
                <label for="date_to" class="form-label">Date to</label>
                <input type="date" id="date_to" name="date_to" value="{{ $memoFilters['date_to'] ?? '' }}" class="form-input w-full" required>
            </div>
            <div>
                <label for="violation_type" class="form-label">Violation</label>
                <select id="violation_type" name="violation_type" class="form-input w-full">
                    @foreach ($violationTypeOptions as $value => $label)
                        <option value="{{ $value }}" @selected(($memoFilters['violation_type'] ?? 'late') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="min_count" class="form-label">Minimum count</label>
                <input type="number" min="1" step="1" id="min_count" name="min_count" value="{{ $memoFilters['min_count'] ?? 1 }}" class="form-input w-full" required>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="btn-primary w-full">Apply Filters</button>
            </div>
        </form>
    </div>

    @if ($memoFilters['applied'] ?? false)
        <div data-memo-root>
            @include('partials.live-data-table', [
                'url' => route(\App\Support\TimekeepingMemo::routeName('index'), request()->query()),
                'search' => $search,
                'searchPlaceholder' => 'Search employee name or number...',
                'searchId' => 'memo-search',
                'paginator' => $paginator,
                'totalLabel' => 'employees',
                'results' => view('timekeeping.memo._results', [
                    'memoFilters' => $memoFilters,
                    'rows' => $rows,
                    'paginator' => $paginator,
                ])->render(),
            ])

        <div id="memo-details-modal-host"></div>
        <div id="memo-preview-modal-host"></div>
        @include('timekeeping.memo._confirm-send-modal')
        </div>
    @else
        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-5 py-10 text-center text-sm text-gray-500">
            Select a date range and violation type, then click <strong>Apply Filters</strong> to list employees.
        </div>
    @endif
@endsection
