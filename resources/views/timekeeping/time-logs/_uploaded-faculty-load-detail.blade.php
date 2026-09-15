@php
    use App\Support\TimeLogs as TimeLogsSupport;

    $showHoursColumn = ($upload->load_type ?? '') === 'SHS';
    $subjectCount = $upload->items->where('row_type', 'subject')->count();
@endphp

<div class="space-y-4">
    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="text-base font-semibold text-gray-900">{{ $upload->faculty_name ?: 'Faculty load' }}</div>
                <div class="mt-1 text-xs text-gray-500">
                    {{ $subjectCount }} section{{ $subjectCount === 1 ? '' : 's' }}
                    @if ($upload->campus_name)
                        | {{ $upload->campus_name }}
                    @endif
                    @if ($upload->term_label)
                        | {{ $upload->term_label }}
                    @endif
                    @if ($upload->employment_type || $upload->appointment_basis)
                        | {{ collect([$upload->employment_type, $upload->appointment_basis])->filter()->implode(' · ') }}
                    @endif
                </div>
            </div>
            <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $upload->parseStatusClass() }}">
                {{ $upload->parseStatusLabel() }}
            </span>
        </div>

        @if ($upload->parse_message && $upload->parse_status !== 'parsed')
            <div class="mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                {{ $upload->parse_message }}
            </div>
        @endif

        <div class="mt-3 flex flex-wrap gap-2">
            <a
                href="{{ route(TimeLogsSupport::routeName('uploads.preview'), $upload) }}"
                target="_blank"
                rel="noopener"
                class="btn-secondary !px-3 !py-1.5 text-xs"
            >
                Open PDF
            </a>
            <a
                href="{{ route(TimeLogsSupport::routeName('uploads.download'), $upload) }}"
                class="btn-secondary !px-3 !py-1.5 text-xs"
            >
                Download PDF
            </a>
            @if ($upload->skolaris_upload_id)
                <span class="inline-flex items-center rounded-full bg-sky-100 px-2 py-0.5 text-[11px] font-semibold text-sky-800">
                    Skolaris #{{ $upload->skolaris_upload_id }}
                </span>
            @endif
        </div>
    </div>

    @if ($upload->items->isEmpty())
        <div class="rounded-lg border border-dashed border-gray-300 px-4 py-10 text-center text-sm text-gray-500">
            No parsed rows were found in this PDF.
        </div>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="table-skolaris min-w-[1100px]">
                <thead>
                    <tr>
                        <th class="w-10 text-center">#</th>
                        <th>Subject code</th>
                        <th>Title</th>
                        <th class="text-center">Class schedule</th>
                        <th class="text-center">Day</th>
                        <th class="text-center">{{ $showHoursColumn ? 'Hours' : 'Units' }}</th>
                        <th class="text-center">Hours paid</th>
                        <th class="text-center">Room</th>
                        <th class="text-center">Sections</th>
                        <th class="text-center">Date range</th>
                        <th class="text-center">Stud count</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($upload->items as $row)
                        <tr @class(['bg-gray-50/60' => $row->row_type !== 'subject'])>
                            <td class="text-center text-xs text-gray-700">
                                {{ $row->row_type === 'subject' ? ($row->row_number ?? '—') : '—' }}
                            </td>
                            <td class="whitespace-nowrap text-xs font-medium text-gray-900">
                                {{ $row->subject_code ?: ($row->row_type !== 'subject' ? strtoupper($row->row_type) : '—') }}
                            </td>
                            <td class="text-xs text-gray-700">{{ $row->title ?: '—' }}</td>
                            <td class="whitespace-pre-line text-center text-xs text-gray-700">{{ $row->class_schedule ?: '—' }}</td>
                            <td class="whitespace-pre-line text-center text-xs text-gray-700">{{ $row->day ?: '—' }}</td>
                            <td class="text-center text-xs text-gray-700">
                                {{ $showHoursColumn ? ($row->hours ?? '—') : ($row->units ?? '—') }}
                            </td>
                            <td class="text-center text-xs text-gray-700">{{ $row->hours_paid ?? '—' }}</td>
                            <td class="whitespace-pre-line text-center text-xs text-gray-700">{{ $row->room ?: '—' }}</td>
                            <td class="whitespace-pre-line text-center text-xs text-gray-700">{{ $row->section ?: '—' }}</td>
                            <td class="text-center text-xs text-gray-700">
                                <div class="whitespace-pre-line">{{ $row->periodRangeLabel() ?: '—' }}</div>
                                @if ($row->schedule_note)
                                    <div class="mt-0.5 text-[10px] font-medium text-red-600">{{ $row->schedule_note }}</div>
                                @endif
                            </td>
                            <td class="text-center text-xs text-gray-700">{{ $row->stud_count ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap justify-end gap-4 text-xs font-semibold text-gray-700">
            @if ($upload->total_hours_week !== null)
                <span>TOTAL HOURS / WEEK: {{ number_format((float) $upload->total_hours_week, 0) }} Hrs</span>
            @endif
            @if ($upload->total_units !== null)
                <span>TOTAL UNITS: {{ $upload->total_units }}</span>
            @endif
        </div>
    @endif
</div>
