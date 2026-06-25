@php
    use App\Support\PayrollTransactionModule;
@endphp

<div data-live-table-total-update data-total="{{ $uploadRecords->total() }}" hidden></div>

<div class="datatable-skolaris-table-wrap">
    <div class="overflow-x-auto">
        <table class="table-skolaris min-w-[900px]">
            <thead>
                <tr>
                    @can('payroll-transaction.delete')
                        <th class="w-10 px-3 py-2">
                            <input type="checkbox" data-payroll-upload-select-all aria-label="Select all batches">
                        </th>
                    @endcan
                    @foreach ($uploadConfig['list_columns'] as $column)
                        <th>{{ $column['label'] }}</th>
                    @endforeach
                    <th class="w-24 px-3 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($uploadRecords as $record)
                    <tr>
                        @can('payroll-transaction.delete')
                            <td class="px-3 py-2">
                                <input
                                    type="checkbox"
                                    name="selected_ids[]"
                                    value="{{ $record->payroll_transaction_id }}"
                                    form="payroll-upload-purge-form"
                                    data-payroll-upload-row-select
                                    aria-label="Select batch {{ $record->batch_no }}"
                                >
                            </td>
                        @endcan
                        @foreach ($uploadConfig['list_columns'] as $column)
                            <td class="{{ $loop->first ? 'font-medium text-gray-900' : 'text-gray-600' }}">
                                {{ PayrollTransactionModule::columnValue($record, $column['key'], $column['type'] ?? null) ?: '—' }}
                            </td>
                        @endforeach
                        <td class="px-3 py-2 text-right">
                            <a
                                href="{{ route(PayrollTransactionModule::routeName('tab'), ['tab' => 'upload-transactions', 'upload' => $uploadType, 'view_upload' => $record->payroll_transaction_id, 'search' => request('search')]) }}"
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
                        <td colspan="{{ count($uploadConfig['list_columns']) + (auth()->user()?->can('payroll-transaction.delete') ? 2 : 1) }}" class="py-12 text-center text-sm text-gray-500">
                            No {{ strtolower($uploadConfig['label']) }} upload batches found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($uploadRecords->hasPages())
    <div class="border-t border-gray-100 px-4 py-3">
        @include('partials.data-table-pagination', ['paginator' => $uploadRecords])
    </div>
@endif
