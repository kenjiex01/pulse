@php
    $forPdf = $forPdf ?? false;
    $sections = $preview['meta']['sections'] ?? [];
    $dayHeaders = $sections[0]['headers'] ?? ($preview['headers'] ?? []);
@endphp

@if (! $forPdf)
    <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">{{ $preview['title'] ?? 'Attendance View' }}</h3>
            @if (! empty($preview['meta']['filter_summary']))
                <p class="text-sm text-gray-600">{{ $preview['meta']['filter_summary'] }}</p>
            @endif
        </div>
        <p class="text-sm text-gray-500">{{ count($sections) }} employee(s)</p>
    </div>
@else
    <h1 style="font-size:16px;font-weight:700;margin:0 0 4px;color:#111;">{{ $preview['title'] ?? 'Attendance View' }}</h1>
    @if (! empty($preview['meta']['filter_summary']))
        <p style="font-size:11px;margin:0 0 12px;color:#333;">{{ $preview['meta']['filter_summary'] }}</p>
    @endif
@endif

<div @class(['space-y-8' => ! $forPdf])>
    @forelse ($sections as $section)
        <div
            @class(['rounded-xl border border-gray-200 bg-white p-4 print:break-after-page' => ! $forPdf])
            style="{{ $forPdf ? (($loop->last ? '' : 'page-break-after:always;').'margin-bottom:16px;') : '' }}"
        >
            <h2 @class(['mb-3 text-base font-semibold text-gray-900' => ! $forPdf]) style="{{ $forPdf ? 'font-size:12px;font-weight:700;margin:0 0 8px;color:#111;' : '' }}">
                {{ $section['heading'] ?? '' }}
            </h2>

            <div @class(['overflow-x-auto' => ! $forPdf])>
                <table @class(['min-w-full divide-y divide-gray-200 text-sm' => ! $forPdf]) style="{{ $forPdf ? 'width:100%;border-collapse:collapse;font-size:9px;color:#111;' : '' }}">
                    <thead @class(['bg-slate-50' => ! $forPdf])>
                        <tr>
                            @foreach ($section['headers'] ?? $dayHeaders as $header)
                                <th @class(['whitespace-nowrap px-3 py-2 text-left font-semibold text-gray-700' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #999;padding:4px 5px;text-align:left;background:#f3f4f6;font-weight:600;' : '' }}">
                                    {{ $header }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody @class(['divide-y divide-gray-100 bg-white' => ! $forPdf])>
                        @forelse ($section['rows'] ?? [] as $row)
                            <tr @class(['hover:bg-slate-50/70' => ! $forPdf])>
                                @foreach ($row as $cell)
                                    <td @class(['whitespace-nowrap px-3 py-2 text-gray-800' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px 5px;color:#111;' : '' }}">
                                        {{ $cell }}
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="{{ max(count($section['headers'] ?? $dayHeaders), 1) }}"
                                    @class(['px-3 py-8 text-center text-gray-500' => ! $forPdf])
                                    style="{{ $forPdf ? 'border:1px solid #ccc;padding:12px;text-align:center;color:#555;' : '' }}"
                                >
                                    No attendance rows for this employee.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <p @class(['text-sm text-gray-500' => ! $forPdf]) style="{{ $forPdf ? 'font-size:11px;color:#555;' : '' }}">
            No employees selected.
        </p>
    @endforelse
</div>
