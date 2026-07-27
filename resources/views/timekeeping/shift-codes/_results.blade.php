@php
    use App\Support\ShiftCode as ShiftCodeSupport;
@endphp

<div data-live-table-total-update data-total="{{ $records->total() }}" hidden></div>

<div class="datatable-skolaris-table-wrap">
    <div class="overflow-x-auto">
        <table class="table-skolaris min-w-[720px]">
            <thead>
                <tr>
                    <th>Shift Code</th>
                    <th>Description</th>
                    <th>Type</th>
                    <th>Break In</th>
                    <th>Break Out</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr>
                        <td class="font-medium text-gray-900">{{ $record->shift_code }}</td>
                        <td class="text-gray-600">{{ $record->description }}</td>
                        <td class="text-gray-600">
                            @if ($record->is_flexi_time)
                                <span class="inline-flex rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-800">
                                    Flexi {{ rtrim(rtrim(number_format((float) ($record->expected_hours_per_day ?? 8), 2, '.', ''), '0'), '.') }}h
                                </span>
                            @else
                                Fixed
                            @endif
                        </td>
                        <td class="text-gray-600">{{ $record->time_in !== '00:00' ? $record->time_in : '—' }}</td>
                        <td class="text-gray-600">{{ $record->time_out !== '00:00' ? $record->time_out : '—' }}</td>
                        <td>
                            <div class="flex items-center justify-end gap-1.5">
                                @can('timekeeping-policy.update')
                                    <button type="button" data-modal-open="shift-code-edit-{{ $record->shift_code_id }}" class="btn-icon" title="Edit">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                @endcan
                                @can('timekeeping-policy.delete')
                                    <form
                                        method="POST"
                                        action="{{ route(ShiftCodeSupport::routeName('destroy'), $record->shift_code_id) }}"
                                        onsubmit="return confirm('Delete this shift code?')"
                                        class="inline"
                                    >
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
                        <td colspan="6" class="py-12 text-center text-sm text-gray-500">No shift codes found.</td>
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
        @can('timekeeping-policy.update')
            @include('partials.modal', [
                'id' => 'shift-code-edit-'.$record->shift_code_id,
                'title' => 'Edit Shift Code',
                'description' => 'Update shift code and break settings',
                'open' => (string) ($openEditId ?? '') === (string) $record->shift_code_id,
                'body' => view('timekeeping.shift-codes._form', [
                    'record' => $record->loadMissing('breaks'),
                    'isEdit' => true,
                    'formContext' => 'edit-shift-code-'.$record->shift_code_id,
                ])->render(),
            ])
        @endcan
    @endforeach
</div>
