@php
    use App\Support\TimeLogs as TimeLogsSupport;
@endphp

<div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_12rem]">
    <div>
        <label for="uploaded-load-search" class="form-label">Search</label>
        <input
            id="uploaded-load-search"
            type="search"
            value="{{ $search ?? '' }}"
            placeholder="Faculty, campus, term, filename..."
            class="form-input w-full"
            data-live-table-search
            autocomplete="off"
        >
    </div>
    <div>
        <label for="uploaded-load-parse-status" class="form-label">Parse status</label>
        <select id="uploaded-load-parse-status" name="parse_status" class="form-input w-full" data-live-table-filter>
            <option value="all" @selected(($parseStatus ?? '') === '' || ($parseStatus ?? '') === 'all')>All</option>
            <option value="parsed" @selected(($parseStatus ?? '') === 'parsed')>Parsed</option>
            <option value="partial" @selected(($parseStatus ?? '') === 'partial')>Partial</option>
            <option value="failed" @selected(($parseStatus ?? '') === 'failed')>Failed</option>
            <option value="pending" @selected(($parseStatus ?? '') === 'pending')>Pending</option>
        </select>
    </div>
</div>

<div class="mt-3 rounded-lg border border-sky-100 bg-sky-50 px-3 py-2 text-xs text-sky-900">
    Upload and parse faculty loading PDFs in Skolaris, then pull them here into People360. Review partial parses against the original file.
</div>
