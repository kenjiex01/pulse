@php
    use App\Support\TimeLogs as TimeLogsSupport;
@endphp

<div data-live-table-total-update data-total="{{ $records->total() }}" hidden></div>

<div class="datatable-skolaris-table-wrap">
    <div class="overflow-x-auto">
        <table class="table-skolaris min-w-[980px]">
            <thead>
                <tr>
                    <th>Faculty</th>
                    <th>Campus / Term</th>
                    <th>Filename</th>
                    <th>Parse status</th>
                    <th>Pulled</th>
                    <th>Pulled by</th>
                    <th class="w-28 px-3 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr>
                        <td class="font-medium text-gray-900">
                            <div>{{ $record->faculty_name ?: '—' }}</div>
                            @if ($record->employee_number)
                                <div class="text-xs text-gray-500">{{ $record->employee_number }}</div>
                            @endif
                        </td>
                        <td class="text-gray-600">
                            <div>{{ $record->campus_name ?: '—' }}</div>
                            <div class="text-xs text-gray-500">{{ $record->term_label ?: '—' }}</div>
                        </td>
                        <td class="text-gray-600">
                            <div class="max-w-[220px] truncate" title="{{ $record->original_filename }}">{{ $record->original_filename }}</div>
                            <div class="text-xs text-gray-500">{{ $record->items_count }} row{{ $record->items_count === 1 ? '' : 's' }}</div>
                        </td>
                        <td>
                            <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $record->parseStatusClass() }}">
                                {{ $record->parseStatusLabel() }}
                            </span>
                            @if ($record->parse_message && $record->parse_status !== 'parsed')
                                <div class="mt-1 max-w-[220px] text-[11px] text-gray-500">{{ $record->parse_message }}</div>
                            @endif
                        </td>
                        <td class="text-gray-600">{{ ($record->pulled_at ?? $record->created_at)?->format('M j, Y g:i A') ?: '—' }}</td>
                        <td class="text-gray-600">
                            <div>{{ $record->puller?->name ?: '—' }}</div>
                            @if ($record->skolaris_uploader_name)
                                <div class="text-xs text-gray-500">Skolaris: {{ $record->skolaris_uploader_name }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right">
                            <div class="inline-flex items-center gap-1">
                                <a
                                    href="{{ route(TimeLogsSupport::routeName('tab'), ['tab' => $tab, 'load_source' => TimeLogsSupport::LOAD_SOURCE_UPLOADED, 'view_upload' => $record->upload_id, 'search' => request('search'), 'parse_status' => request('parse_status')]) }}"
                                    class="btn-icon"
                                    title="View parsed load"
                                    data-no-loader
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                                @can('time-logs.delete')
                                    <form
                                        method="POST"
                                        action="{{ route(TimeLogsSupport::routeName('uploads.destroy'), $record) }}"
                                        class="inline"
                                        onsubmit="return confirm('Delete this uploaded faculty load?');"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="search" value="{{ request('search') }}">
                                        <input type="hidden" name="parse_status" value="{{ request('parse_status') }}">
                                        <button type="submit" class="btn-icon text-red-600 hover:text-red-700" title="Delete upload">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-12 text-center">
                            <div class="text-sm font-semibold text-gray-700">No uploaded loads pulled yet</div>
                            <div class="mt-1 text-sm text-gray-500">Use Pull from Skolaris to sync faculty loading PDFs uploaded in Skolaris.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="datatable-skolaris-pagination mt-4">
    @include('partials.data-table-pagination', ['paginator' => $records])
</div>
