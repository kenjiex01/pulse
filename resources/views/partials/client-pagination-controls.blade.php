@props([
    'defaultPageSize' => 20,
])

<div class="mt-3 flex flex-wrap items-center justify-between gap-3" data-paginate-controls>
    <p class="text-xs text-gray-500" data-paginate-info></p>

    <div class="flex flex-wrap items-center gap-2">
        <label class="flex items-center gap-1.5 text-xs text-gray-600" data-paginate-per-page-wrap>
            <span>Show:</span>
            <select data-paginate-per-page class="form-select !w-auto !py-1 !text-xs">
                @foreach ([10, 25, 50, 100] as $size)
                    <option value="{{ $size }}" @selected($size === (int) $defaultPageSize)>{{ $size }} per page</option>
                @endforeach
            </select>
        </label>

        <button type="button" data-paginate-show-all class="btn-secondary !px-3 !py-1.5 text-xs">Show all</button>

        <div class="flex items-center gap-1" data-paginate-nav>
            <button type="button" data-paginate-prev class="btn-secondary !px-3 !py-1.5 text-xs">Previous</button>
            <div class="flex items-center gap-1" data-paginate-pages></div>
            <button type="button" data-paginate-next class="btn-secondary !px-3 !py-1.5 text-xs">Next</button>
        </div>
    </div>
</div>
