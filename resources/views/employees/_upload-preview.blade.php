@if (! $staging)
    <p class="text-sm text-gray-600">Upload preview expired. Please upload the file again.</p>
@else
    <div class="space-y-4 text-sm">
        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                <p class="text-xs text-gray-500">File</p>
                <p class="mt-1 font-medium text-gray-900">{{ $staging['filename'] ?? '—' }}</p>
            </div>
            <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-3">
                <p class="text-xs text-emerald-700">Valid Rows</p>
                <p class="mt-1 text-lg font-bold text-emerald-800">{{ $staging['valid_count'] ?? 0 }}</p>
            </div>
            <div class="rounded-lg border border-red-100 bg-red-50 p-3">
                <p class="text-xs text-red-700">Errors</p>
                <p class="mt-1 text-lg font-bold text-red-800">{{ $staging['error_count'] ?? 0 }}</p>
            </div>
        </div>

        @if (($staging['valid_count'] ?? 0) > 0)
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Valid Records Preview</p>
                <div data-client-paginate data-page-size="20">
                    <div class="max-h-56 overflow-auto rounded-lg border border-gray-100">
                        <table class="min-w-full divide-y divide-gray-100 text-xs">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-600">Employee #</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-600">Name</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-600">Email</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-600">Campus</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 bg-white">
                                @foreach ($staging['valid'] ?? [] as $row)
                                    @php($preview = $row['preview'] ?? $row)
                                    <tr data-paginate-row>
                                        <td class="px-3 py-2 text-gray-700">{{ $preview['employee_number'] ?: '(auto)' }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ trim(($preview['first_name'] ?? '').' '.($preview['last_name'] ?? '')) }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $preview['email'] ?? '—' }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $preview['campus_code'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if (count($staging['valid'] ?? []) > 1)
                        @include('partials.client-pagination-controls', ['defaultPageSize' => 20])
                    @endif
                </div>
            </div>
        @endif

        @if (($staging['error_count'] ?? 0) > 0)
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-red-600">Errors</p>
                <ul class="max-h-40 space-y-1 overflow-auto rounded-lg border border-red-100 bg-red-50 p-3 text-xs text-red-800">
                    @foreach (array_slice($staging['errors'] ?? [], 0, 20) as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                    @if (count($staging['errors'] ?? []) > 20)
                        <li class="italic">…and {{ count($staging['errors']) - 20 }} more</li>
                    @endif
                </ul>
            </div>
        @endif

        <div class="flex flex-wrap justify-end gap-2 border-t border-gray-100 pt-4">
            <form method="POST" action="{{ route('employees.upload.discard') }}">
                @csrf
                <input type="hidden" name="staging_token" value="{{ $stagingToken }}">
                <button type="submit" class="btn-secondary">Discard</button>
            </form>
            @if (($staging['valid_count'] ?? 0) > 0)
                <form method="POST" action="{{ route('employees.upload.commit') }}">
                    @csrf
                    <input type="hidden" name="staging_token" value="{{ $stagingToken }}">
                    <button type="submit" class="btn-primary">Import {{ $staging['valid_count'] }} Employee(s)</button>
                </form>
            @endif
        </div>
    </div>
@endif
