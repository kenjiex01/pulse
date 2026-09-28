@php
    use App\Support\TimeLogs;
@endphp

@can('time-logs.create')
    @if ($s3PullConfigured ?? false)
        <div class="mb-4 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
            <form
                method="POST"
                action="{{ route(TimeLogs::routeName('s3-auto-pull')) }}"
                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                data-biometric-s3-auto-pull-form
            >
                @csrf
                <input type="hidden" name="tab" value="{{ $tab }}">
                <input type="hidden" name="enabled" value="0">

                <label class="flex cursor-pointer items-start gap-3">
                    <input
                        type="checkbox"
                        name="enabled"
                        value="1"
                        class="mt-0.5 rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]"
                        @checked($s3AutoPullEnabled ?? false)
                        data-biometric-s3-auto-pull-checkbox
                    >
                    <span>
                        <span class="block text-sm font-medium text-gray-900">Auto-pull new biometric logs from S3</span>
                        <span class="mt-1 block text-xs text-gray-500">
                            When enabled, People360 checks S3 every few minutes for new
                            <code class="rounded bg-gray-100 px-1">biometric_logs/</code> uploads and imports them automatically.
                            Each file is marked as pulled so it is not imported again.
                            Manual <strong>Pull logs</strong> still works for a chosen month or collector.
                        </span>
                        @if ($s3AutoPullLastRunAt ?? null)
                            <span class="mt-1 block text-xs text-gray-400">
                                Last auto-pull check: {{ \Carbon\Carbon::parse($s3AutoPullLastRunAt)->format('M j, Y g:i A') }}
                            </span>
                        @endif
                    </span>
                </label>

                <button type="submit" class="btn-secondary shrink-0 text-sm">Save</button>
            </form>
        </div>
    @endif
@endcan
