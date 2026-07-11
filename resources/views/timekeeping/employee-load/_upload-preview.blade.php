@php
    use App\Support\TimekeepingEmployeeLoad;
@endphp

@if (empty($staging))
    <p class="text-sm text-gray-600">Upload preview expired. Please upload the file again.</p>
@else
    <div class="space-y-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-3">
                <p class="text-xs text-gray-500">Enrollment Period</p>
                <p class="mt-1 font-medium text-gray-900">{{ $staging['enrollment_period_label'] ?: '—' }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-3">
                <p class="text-xs text-gray-500">File Name</p>
                <p class="mt-1 font-medium text-gray-900">{{ $staging['filename'] ?? '—' }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-3">
                <p class="text-xs text-gray-500">Valid / Invalid Rows</p>
                <p class="mt-1 font-medium text-gray-900">
                    {{ $staging['valid_count'] ?? 0 }} / {{ $staging['error_count'] ?? 0 }}
                </p>
            </div>
        </div>

        @if (! empty($staging['warnings']))
            <div>
                <h3 class="mb-2 text-sm font-semibold text-amber-700">Warnings</h3>
                <div class="max-h-32 overflow-y-auto rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($staging['warnings'] as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @if (! empty($staging['errors']))
            <div>
                <h3 class="mb-2 text-sm font-semibold text-red-700">Validation Errors</h3>
                <div class="max-h-40 overflow-y-auto rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($staging['errors'] as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @if (! empty($staging['valid']))
            <div>
                <h3 class="mb-2 text-sm font-semibold text-gray-900">Valid Records Preview</h3>
                <div class="max-h-72 overflow-auto rounded-lg border border-gray-200">
                    <table class="table-skolaris min-w-full text-sm">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left">#</th>
                                <th class="px-3 py-2 text-left">Faculty</th>
                                <th class="px-3 py-2 text-left">Subject</th>
                                <th class="px-3 py-2 text-left">Section</th>
                                <th class="px-3 py-2 text-left">Date</th>
                                <th class="px-3 py-2 text-left">Time In</th>
                                <th class="px-3 py-2 text-left">Time Out</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (array_slice($staging['valid'], 0, 25) as $index => $row)
                                <tr>
                                    <td class="px-3 py-2 text-gray-600">{{ $index + 1 }}</td>
                                    <td class="px-3 py-2 text-gray-900">{{ $row['faculty_name'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-gray-600">{{ $row['subject'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-gray-600">{{ $row['section'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-gray-600">{{ $row['load_date'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-gray-600">{{ $row['time_in'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-gray-600">{{ $row['time_out'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if (($staging['valid_count'] ?? 0) > 25)
                    <p class="mt-2 text-xs text-gray-500">Showing first 25 of {{ $staging['valid_count'] }} valid rows.</p>
                @endif
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-gray-100 pt-4">
            <form method="POST" action="{{ route(TimekeepingEmployeeLoad::routeName('upload.discard')) }}">
                @csrf
                <input type="hidden" name="staging_token" value="{{ $stagingToken }}">
                <button type="submit" class="btn-secondary">Re-upload File</button>
            </form>

            @if (($staging['valid_count'] ?? 0) > 0)
                <form method="POST" action="{{ route(TimekeepingEmployeeLoad::routeName('upload.commit')) }}">
                    @csrf
                    <input type="hidden" name="staging_token" value="{{ $stagingToken }}">
                    <button type="submit" class="btn-primary">Load to the Database</button>
                </form>
            @endif
        </div>
    </div>
@endif
