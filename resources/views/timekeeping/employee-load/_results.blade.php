@php
    use App\Support\TimekeepingEmployeeLoad;

    $canDelete = auth()->user()?->can('employee-load.delete');
    $columnCount = count($listColumns) + ($canDelete ? 2 : 1);
@endphp

<div data-live-table-total-update data-total="{{ $records->total() }}" hidden></div>

<div class="datatable-skolaris-table-wrap">
    <div class="overflow-x-auto">
        <table class="table-skolaris min-w-[820px]">
            <thead>
                <tr>
                    @if ($canDelete)
                        <th class="w-10 px-3 py-2">
                            <input type="checkbox" data-employee-load-select-all aria-label="Select all batches">
                        </th>
                    @endif
                    @foreach ($listColumns as $column)
                        <th>{{ $column['label'] }}</th>
                    @endforeach
                    <th class="w-24 px-3 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr>
                        @if ($canDelete)
                            <td class="px-3 py-2">
                                <input
                                    type="checkbox"
                                    name="selected_ids[]"
                                    value="{{ $record->employee_load_transaction_id }}"
                                    form="employee-load-purge-form"
                                    data-employee-load-row-select
                                    aria-label="Select batch {{ $record->batch_no }}"
                                >
                            </td>
                        @endif
                        @foreach ($listColumns as $column)
                            <td class="{{ $loop->first ? 'font-medium text-gray-900' : 'text-gray-600' }}">
                                {{ TimekeepingEmployeeLoad::columnValue($record, $column['key'], $column['type'] ?? null) ?: '—' }}
                            </td>
                        @endforeach
                        <td class="px-3 py-2 text-right">
                            <a
                                href="{{ route(TimekeepingEmployeeLoad::routeName('index'), ['view' => $record->employee_load_transaction_id, 'search' => request('search')]) }}"
                                class="btn-icon"
                                title="View batch"
                                data-no-loader
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $columnCount }}" class="py-12 text-center text-sm text-gray-500">
                            No employee load batches uploaded yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
