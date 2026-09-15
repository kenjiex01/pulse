<form
    method="POST"
    action="{{ route(\App\Support\TimeLogs::routeName('uploads.pull')) }}"
    class="space-y-4"
>
    @csrf

    <p class="text-sm text-gray-600">
        Pull uploaded faculty loading PDFs from Skolaris into People360. Upload and parsing happen in Skolaris only — People360 caches a local copy for review and offline use.
    </p>

    <div>
        <label for="uploaded-load-pull-parse-status" class="form-label">Parse status filter</label>
        <select id="uploaded-load-pull-parse-status" name="parse_status" class="form-input w-full">
            <option value="all" @selected(old('parse_status', 'all') === 'all')>All</option>
            <option value="parsed" @selected(old('parse_status') === 'parsed')>Parsed</option>
            <option value="partial" @selected(old('parse_status') === 'partial')>Partial</option>
            <option value="failed" @selected(old('parse_status') === 'failed')>Failed</option>
            <option value="pending" @selected(old('parse_status') === 'pending')>Pending</option>
        </select>
    </div>

    <label class="flex items-start gap-2 text-sm text-gray-700">
        <input type="hidden" name="refresh_existing" value="0">
        <input
            type="checkbox"
            name="refresh_existing"
            value="1"
            class="mt-1 rounded border-gray-300 text-[#0B318F] focus:ring-[#0B318F]"
            @checked(old('refresh_existing'))
        >
        <span>Refresh records already cached in People360, even when Skolaris has no newer changes.</span>
    </label>

    @include('partials.modal-form-actions', [
        'submitLabel' => 'Pull from Skolaris',
        'cancelModalId' => 'time-logs-uploaded-load-pull-modal',
    ])
</form>
