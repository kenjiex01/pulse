@php
    $primaryKey = $config['primary_key'];
    $isDayTypes = $tab === 'day-types';
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
                    <tr>
                        @foreach ($config['columns'] as $column)
                            <td class="{{ $loop->first ? 'font-medium text-gray-900' : 'text-gray-600' }}">
                                {{ \App\Support\RateDefinition::columnValue($record, $column['key'], $column['type'] ?? null) ?: '—' }}
                            </td>
                        @endforeach
                        <td>
                            <div class="flex items-center justify-end gap-1.5">
                                @can('rate-definition.update', $record)
                                    @if ($isDayTypes)
                                        <button type="button" data-modal-open="rate-definition-edit-{{ $tab }}-{{ $record->{$primaryKey} }}" class="btn-icon" title="Edit">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                    @else
                                        @php
                                            $editRoute = $tab === 'rate-groups'
                                                ? route('payroll.rate-definitions.rate-groups.edit', $record->{$primaryKey})
                                                : route('payroll.rate-definitions.nd-rate-groups.edit', $record->{$primaryKey});
                                        @endphp
                                        <a href="{{ $editRoute }}" class="btn-icon" title="Edit">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                    @endif
                                @endcan
                                @can('rate-definition.delete', $record)
                                    <form method="POST" action="{{ route('payroll.rate-definitions.destroy', ['tab' => $tab, 'record' => $record->{$primaryKey}]) }}" onsubmit="return confirm('Delete this {{ strtolower($config['name']) }}?')" class="inline">
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

@if ($isDayTypes)
    <div data-live-table-modals>
        @foreach ($records as $record)
            @can('rate-definition.update', $record)
                @include('partials.modal', [
                    'id' => "rate-definition-edit-$tab-{$record->{$primaryKey}}",
                    'title' => 'Edit '.$config['name'],
                    'description' => 'Update record details',
                    'open' => (string) ($openEditId ?? '') === (string) $record->{$primaryKey},
                    'body' => view('payroll.rate-definitions._form-day-types', [
                        'record' => $record,
                        'isEdit' => true,
                        'formContext' => "edit-$tab-{$record->{$primaryKey}}",
                        'selectOptions' => $selectOptions,
                    ])->render(),
                ])
            @endcan
        @endforeach
    </div>
@endif
