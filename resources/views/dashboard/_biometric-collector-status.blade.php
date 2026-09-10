@php
    $status = $biometricCollectorStatus ?? null;
    $collected = $status['collected_today'] ?? [];
    $missing = $status['missing_today'] ?? [];
    $unmapped = $status['unmapped_collectors'] ?? [];
@endphp

<div class="mt-8 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Biometric Collector — S3 status</h2>
            <p class="mt-1 text-sm text-gray-600">
                Live view of <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">biometric_logs/</code> uploads
                for <strong>{{ $status['reference_date_label'] ?? 'today' }}</strong>
                @if (! empty($status['bucket']))
                    · bucket <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">{{ $status['bucket'] }}</code>
                    @if (! empty($status['region']))
                        ({{ $status['region'] }})
                    @endif
                @endif
            </p>
        </div>
        @if (! empty($timeLogsPullUrl))
            <a
                href="{{ $timeLogsPullUrl }}"
                class="inline-flex items-center justify-center rounded-lg border border-[#0B318F] px-3 py-2 text-sm font-medium text-[#0B318F] no-underline transition hover:bg-[#0B318F] hover:text-white"
            >
                Pull logs into Time Logs
            </a>
        @endif
    </div>

    @if (! ($status['configured'] ?? false))
        <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
            {{ $status['error'] ?? 'S3 is not configured for biometric collector logs.' }}
        </p>
    @elseif (! empty($status['error']))
        <p class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
            {{ $status['error'] }}
        </p>
    @else
        <div class="mb-4 flex flex-wrap gap-3 text-sm">
            <span class="inline-flex items-center gap-2 rounded-full bg-green-50 px-3 py-1 font-medium text-green-800">
                <span class="h-2 w-2 rounded-full bg-green-500"></span>
                Collected today — {{ count($collected) }}
            </span>
            <span class="inline-flex items-center gap-2 rounded-full bg-amber-50 px-3 py-1 font-medium text-amber-900">
                <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                No upload today — {{ count($missing) }}
            </span>
            @if ($unmapped !== [])
                <span class="inline-flex items-center gap-2 rounded-full bg-gray-100 px-3 py-1 font-medium text-gray-700">
                    Unmapped collector folder — {{ count($unmapped) }}
                </span>
            @endif
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div>
                <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-green-800">Collected today</h3>
                @if ($collected === [])
                    <p class="text-sm text-gray-500">No campus uploads found in S3 for this date yet.</p>
                @else
                    <div class="overflow-x-auto rounded-lg border border-green-100">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-green-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700">Campus</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700">Collector folder</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700">Latest upload (today)</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700">Last S3 upload (month)</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700">Last pulled into People360</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach ($collected as $row)
                                    <tr>
                                        <td class="px-3 py-2">
                                            <div class="font-medium text-gray-900">{{ $row['campus_name'] }}</div>
                                            <div class="text-xs text-gray-500">{{ $row['campus_code'] }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-xs text-gray-700">
                                            {{ implode(', ', $row['collector_folders'] ?? []) }}
                                            @if (($row['file_count'] ?? 0) > 1)
                                                <span class="text-gray-500">({{ $row['file_count'] }} files)</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-gray-700">{{ $row['latest_collect_label'] }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $row['last_s3_collect_label'] }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $row['last_pulled_label'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div>
                <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-amber-800">No upload today</h3>
                @if ($missing === [])
                    <p class="text-sm text-gray-500">All active campuses have collector uploads for this date.</p>
                @else
                    <div class="overflow-x-auto rounded-lg border border-amber-100">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-amber-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700">Campus</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700">Last S3 upload (month)</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700">Last pulled into People360</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach ($missing as $row)
                                    <tr>
                                        <td class="px-3 py-2">
                                            <div class="font-medium text-gray-900">{{ $row['campus_name'] }}</div>
                                            <div class="text-xs text-gray-500">{{ $row['campus_code'] }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-gray-700">{{ $row['last_s3_collect_label'] }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $row['last_pulled_label'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        @if ($unmapped !== [])
            <div class="mt-6">
                <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-700">Unmapped collector folders (today)</h3>
                <p class="mb-2 text-xs text-gray-500">S3 has uploads but folder name did not match a campus. Rename collector folder or add campus mapping.</p>
                <div class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700">Collector folder</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700">Latest upload</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700">Files</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @foreach ($unmapped as $row)
                                <tr>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $row['collector_folder'] }}</td>
                                    <td class="px-3 py-2 text-gray-700">{{ $row['latest_collect_label'] }}</td>
                                    <td class="px-3 py-2 text-gray-700">{{ $row['file_count'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div>
