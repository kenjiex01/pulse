@php
    use App\Support\TimeLogs;
@endphp

<form
    method="POST"
    action="{{ route(TimeLogs::routeName('s3-pull')) }}"
    class="space-y-4"
    data-biometric-s3-pull-form
    data-folders-url="{{ route(TimeLogs::routeName('s3-folders')) }}"
>
    @csrf
    <input type="hidden" name="tab" value="{{ $tab }}">

    @if (! ($s3PullConfigured ?? false))
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
            S3 credentials are missing. Configure <code class="rounded bg-white px-1 text-xs">DB_BACKUP_S3_KEY</code>,
            <code class="rounded bg-white px-1 text-xs">DB_BACKUP_S3_SECRET</code>, and
            <code class="rounded bg-white px-1 text-xs">DB_BACKUP_S3_BUCKET</code>
            (same bucket as <code class="rounded bg-white px-1 text-xs">biometric_logs/</code>).
        </div>
    @endif

    <p class="text-sm text-gray-600">
        Downloads gzipped attendance JSON from
        <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">biometric_logs/YYYY/MM/{collector}/</code>
        on S3. Each log <code class="rounded bg-gray-100 px-1 text-xs">user_id</code> is matched to the employee
        <strong>biometric ID</strong> for the campus in the file (or the campus you select below).
    </p>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label class="form-label" for="biometric-s3-year">Year <span class="text-red-500">*</span></label>
            <input
                id="biometric-s3-year"
                type="number"
                name="year"
                class="form-input"
                min="2000"
                max="2100"
                required
                value="{{ old('year', $s3PullYear ?? now()->year) }}"
                data-biometric-s3-year
            >
            @error('year')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="form-label" for="biometric-s3-month">Month <span class="text-red-500">*</span></label>
            <select id="biometric-s3-month" name="month" class="form-input" required data-no-searchable-select data-biometric-s3-month>
                @for ($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" @selected((int) old('month', $s3PullMonth ?? now()->month) === $m)>
                        {{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}
                    </option>
                @endfor
            </select>
            @error('month')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div>
        <label class="form-label" for="biometric-s3-campus">Campus</label>
        <select id="biometric-s3-campus" name="campus_id" class="form-input" data-no-searchable-select>
            <option value="">All campuses (use campus_code from each JSON file)</option>
            @foreach ($s3PullCampuses ?? [] as $campus)
                <option value="{{ $campus->campus_id }}" @selected((string) old('campus_id') === (string) $campus->campus_id)>
                    {{ $campus->campus_name }} ({{ $campus->campus_code }})
                </option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-gray-500">
            Optional filter. Example: Cainta Main only imports files whose campus code is CA and matches biometric IDs on that campus.
        </p>
        @error('campus_id')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="form-label" for="biometric-s3-folder">Collector folder</label>
        <select id="biometric-s3-folder" name="collector_folder" class="form-input" data-no-searchable-select data-biometric-s3-folder>
            <option value="">All collectors for the selected month</option>
            @if (old('collector_folder'))
                <option value="{{ old('collector_folder') }}" selected>{{ old('collector_folder') }}</option>
            @endif
        </select>
        <p class="mt-1 text-xs text-gray-500" data-biometric-s3-folder-hint>
            Folders load from S3 when you change year/month (e.g. <code class="rounded bg-gray-100 px-1">Cainta-Main-Campus</code>).
        </p>
        @error('collector_folder')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    @include('partials.modal-form-actions', [
        'submitLabel' => 'Pull logs',
        'cancelModalId' => 'time-logs-s3-pull-modal',
        'submitDisabled' => ! ($s3PullConfigured ?? false),
    ])
</form>
