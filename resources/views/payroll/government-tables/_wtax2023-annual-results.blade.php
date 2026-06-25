@php
    $primaryKey = 'govt_table_wtax_annual_2023_id';
@endphp

<div data-live-table-total-update data-total="{{ $records->total() }}" hidden></div>

<div class="datatable-skolaris-table-wrap">
    <div class="overflow-x-auto">
        <table class="table-skolaris min-w-[720px]">
            <thead>
                <tr>
                    <th>Income From</th>
                    <th>Income To</th>
                    <th>Tax Amount Due</th>
                    <th>Tax Percentage Due</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr>
                        <td class="font-medium text-gray-900">{{ number_format((float) $record->income_from, 2) }}</td>
                        <td class="text-gray-600">{{ number_format((float) $record->income_to, 2) }}</td>
                        <td class="text-gray-600">{{ number_format((float) $record->amount_due, 2) }}</td>
                        <td class="text-gray-600">{{ number_format((float) $record->percentage_due, 2) }}%</td>
                        <td>
                            <div class="flex items-center justify-end gap-1.5">
                                @can('government-tables.update', $record)
                                    <button type="button" data-modal-open="government-tables-edit-wtax-annual-{{ $record->{$primaryKey} }}" class="btn-icon" title="Edit">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                @endcan
                                @can('government-tables.delete', $record)
                                    <form method="POST" action="{{ route('payroll.government-tables.wtax2023-annual.destroy', $record->{$primaryKey}) }}" onsubmit="return confirm('Delete this annual tax range?')" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-icon text-red-500 hover:bg-red-50 hover:text-red-600" title="Delete">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-12 text-center text-sm text-gray-500">No annual tax ranges found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="datatable-skolaris-pagination mt-4">
    @include('partials.data-table-pagination', ['paginator' => $records])
</div>

<div data-live-table-modals>
    @foreach ($records as $record)
        @can('government-tables.update', $record)
            @include('partials.modal', [
                'id' => "government-tables-edit-wtax-annual-{$record->{$primaryKey}}",
                'title' => 'Edit Annual Tax Range',
                'description' => 'Update annual withholding tax range',
                'open' => (string) ($openEditId ?? '') === (string) $record->{$primaryKey},
                'body' => view('payroll.government-tables._form-wtax-annual', [
                    'record' => $record,
                    'isEdit' => true,
                    'formContext' => "edit-wtax-annual-{$record->{$primaryKey}}",
                ])->render(),
            ])
        @endcan
    @endforeach
</div>
