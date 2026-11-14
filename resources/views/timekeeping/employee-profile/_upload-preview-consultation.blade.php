@php
    use App\Support\TimekeepingEmployeeProfile;
    use Carbon\CarbonImmutable;

    $dayNames = TimekeepingEmployeeProfile::consultationWeekDays()->pluck('day', 'day_id');
    $clock = fn (string $time) => CarbonImmutable::createFromFormat('H:i', $time)->format('g:i A');
    $conflictCount = collect($staging['valid'] ?? [])->filter(fn (array $row) => ! empty($row['conflicts']))->count();
@endphp

@if (empty($staging))
    <p class="text-sm text-gray-600">Upload preview expired. Please upload the file again.</p>
@else
    <div class="space-y-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-3">
                <p class="text-xs text-gray-500">File Name</p>
                <p class="mt-1 font-medium text-gray-900">{{ $staging['filename'] ?? '—' }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-3">
                <p class="text-xs text-gray-500">Faculty / Invalid Rows</p>
                <p class="mt-1 font-medium text-gray-900">
                    {{ $staging['valid_count'] ?? 0 }} / {{ $staging['error_count'] ?? 0 }}
                </p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-3">
                <p class="text-xs text-gray-500">Action</p>
                <p class="mt-1 font-medium text-gray-900">Replace consultation hours</p>
            </div>
        </div>

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

        @if ($conflictCount > 0)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                {{ $conflictCount }} faculty have consultation hours that conflict with their last pulled teaching load. They can still be loaded.
            </div>
        @endif

        @if (! empty($staging['valid']))
            <div>
                <h3 class="mb-2 text-sm font-semibold text-gray-900">Valid Records Preview</h3>
                <div data-client-paginate data-page-size="20">
                    <div class="max-h-72 overflow-auto rounded-lg border border-gray-200">
                        <table class="table-skolaris min-w-full text-sm">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2 text-left">#</th>
                                    <th class="px-3 py-2 text-left">Employee No.</th>
                                    <th class="px-3 py-2 text-left">Name</th>
                                    <th class="px-3 py-2 text-left">Consultation Hours</th>
                                    <th class="px-3 py-2 text-left">Teaching Load Conflicts</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($staging['valid'] as $index => $row)
                                    <tr data-paginate-row class="align-top">
                                        <td class="px-3 py-2 text-gray-600">{{ $index + 1 }}</td>
                                        <td class="px-3 py-2 text-gray-900">{{ $row['emp_num'] ?? '—' }}</td>
                                        <td class="px-3 py-2 text-gray-600">{{ $row['full_name'] ?? '—' }}</td>
                                        <td class="px-3 py-2 text-gray-600">
                                            @foreach (collect($row['slots'] ?? [])->groupBy('day_id') as $dayId => $slots)
                                                <div>
                                                    <span class="font-medium text-gray-900">{{ $dayNames->get($dayId) }}:</span>
                                                    {{ $slots->map(fn (array $slot) => $clock($slot['time_start']).'–'.$clock($slot['time_end']))->implode(', ') }}
                                                </div>
                                            @endforeach
                                        </td>
                                        <td class="px-3 py-2 text-amber-800">
                                            @forelse ($row['conflicts'] ?? [] as $conflict)
                                                <div>{{ $conflict }}</div>
                                            @empty
                                                <span class="text-gray-400">None</span>
                                            @endforelse
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if (count($staging['valid']) > 1)
                        @include('partials.client-pagination-controls', ['defaultPageSize' => 20])
                    @endif
                </div>
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-gray-100 pt-4">
            <form method="POST" action="{{ route(TimekeepingEmployeeProfile::routeName('upload.discard')) }}">
                @csrf
                <input type="hidden" name="staging_token" value="{{ $stagingToken }}">
                <button type="submit" class="btn-secondary">Re-upload File</button>
            </form>

            @if (($staging['valid_count'] ?? 0) > 0)
                <form method="POST" action="{{ route(TimekeepingEmployeeProfile::routeName('upload.commit')) }}">
                    @csrf
                    <input type="hidden" name="staging_token" value="{{ $stagingToken }}">
                    <button type="submit" class="btn-primary">Load to the Database</button>
                </form>
            @endif
        </div>
    </div>
@endif
