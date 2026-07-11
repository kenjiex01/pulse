@php
    $primaryKey = $config['primary_key'];
@endphp

<div data-live-table-total-update data-total="{{ $records->total() }}" hidden></div>

<div class="datatable-skolaris-table-wrap">
    <div class="overflow-x-auto">
        <table class="table-skolaris min-w-[720px]">
            <thead>
                <tr>
                    @foreach ($config['columns'] as $column)
                        <th>{{ $column['label'] }}</th>
                    @endforeach
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    @php
                        $isProtected = \App\Support\PayrollMaintenance::isProtectedRecord($record, $tab);
                        $isAlwaysActive = \App\Support\PayrollMaintenance::isAlwaysActiveRecord($record, $tab);
                    @endphp
                    <tr>
                        @foreach ($config['columns'] as $column)
                            <td class="{{ $loop->first ? 'font-medium text-gray-900' : 'text-gray-600' }}">
                                @if ($loop->first && $isProtected)
                                    <span class="mr-2 inline-flex rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-600">Default</span>
                                @endif
                                @if (($column['type'] ?? null) === 'boolean')
                                    <span class="{{ $record->{$column['key']} ? 'font-medium text-gray-900' : 'text-gray-500' }}">
                                        {{ $record->{$column['key']} ? 'Active' : 'Inactive' }}
                                    </span>
                                @else
                                    {{ \App\Support\PayrollMaintenance::columnValue($record, $column['key'], $column['type'] ?? null) ?: '—' }}
                                @endif
                            </td>
                        @endforeach
                        <td>
                            <div class="flex items-center justify-end gap-1.5">
                                @if (! $isProtected)
                                    @can('payroll-maintenance.update', $record)
                                        <button type="button" data-modal-open="payroll-maintenance-edit-{{ $tab }}-{{ $record->{$primaryKey} }}" class="btn-icon" title="Edit">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        @if (! $isAlwaysActive)
                                            <form method="POST" action="{{ route(\App\Support\PayrollMaintenance::routeName('toggle-status'), ['tab' => $tab, 'record' => $record->{$primaryKey}]) }}" class="inline">
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="btn-icon {{ $record->is_active ? '!text-green-600 hover:!text-green-700 hover:bg-green-50' : '!text-gray-400 hover:!text-gray-500 hover:bg-gray-50' }}"
                                                    title="{{ $record->is_active ? 'Set inactive' : 'Set active' }}"
                                                >
                                                    @if ($record->is_active)
                                                        <svg class="h-5 w-5 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="10" rx="5"/><circle cx="17" cy="12" r="3" fill="currentColor" stroke="none"/></svg>
                                                    @else
                                                        <svg class="h-5 w-5 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="10" rx="5"/><circle cx="7" cy="12" r="3" fill="currentColor" stroke="none"/></svg>
                                                    @endif
                                                </button>
                                            </form>
                                        @endif
                                    @endcan
                                    @if (! $record->is_active)
                                        @can('payroll-maintenance.delete', $record)
                                            <form method="POST" action="{{ route(\App\Support\PayrollMaintenance::routeName('destroy'), ['tab' => $tab, 'record' => $record->{$primaryKey}]) }}" onsubmit="return confirm('Delete this inactive record?')" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-icon text-red-500 hover:bg-red-50 hover:text-red-600" title="Delete">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </form>
                                        @endcan
                                    @endif
                                @else
                                    <span class="text-xs text-gray-400">Locked</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($config['columns']) + 1 }}" class="py-12 text-center text-sm text-gray-500">
                            No {{ strtolower($config['name']) }} records found.
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

<div data-live-table-modals>
    @foreach ($records as $record)
        @if (! \App\Support\PayrollMaintenance::isProtectedRecord($record, $tab))
            @can('payroll-maintenance.update', $record)
                @include('partials.modal', [
                'id' => "payroll-maintenance-edit-$tab-{$record->{$primaryKey}}",
                'title' => 'Edit '.$config['name'],
                'description' => 'Update record details',
                'open' => (string) ($openEditId ?? '') === (string) $record->{$primaryKey},
                'body' => view('payroll.maintenance-table._form', [
                    'tab' => $tab,
                    'config' => $config,
                    'record' => $record,
                    'isEdit' => true,
                    'formContext' => "edit-$tab-{$record->{$primaryKey}}",
                    'selectOptions' => $selectOptions,
                ])->render(),
            ])
            @endcan
        @endif
    @endforeach
</div>
