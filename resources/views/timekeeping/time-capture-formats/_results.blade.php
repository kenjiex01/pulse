@php
    use App\Support\TimeCaptureFormat as TimeCaptureFormatSupport;
@endphp

<div data-live-table-total-update data-total="{{ $records->total() }}" hidden></div>

<div class="datatable-skolaris-table-wrap">
    <div class="overflow-x-auto">
        <table class="table-skolaris min-w-[720px]">
            <thead>
                <tr>
                    <th>Device Name</th>
                    <th>Description</th>
                    <th>Fields</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr>
                        <td class="font-medium text-gray-900">{{ $record->device_name }}</td>
                        <td class="text-gray-600">{{ $record->description }}</td>
                        <td class="text-gray-600">{{ $record->fields_count }}</td>
                        <td>
                            <div class="flex items-center justify-end gap-1.5">
                                @can('timekeeping-policy.update')
                                    <button type="button" data-modal-open="time-capture-format-edit-{{ $record->timecapture_format_id }}" class="btn-icon" title="Edit">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                @endcan
                                @can('timekeeping-policy.delete')
                                    <form
                                        method="POST"
                                        action="{{ route(TimeCaptureFormatSupport::routeName('destroy'), $record->timecapture_format_id) }}"
                                        onsubmit="return confirm('Delete this time capture format?')"
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
                        <td colspan="4" class="py-12 text-center text-sm text-gray-500">No time capture formats found.</td>
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
                'id' => 'time-capture-format-edit-'.$record->timecapture_format_id,
                'title' => 'Edit Time Capture Format',
                'description' => 'Update device format and column mappings',
                'panelClass' => 'modal-panel-lg',
                'open' => (string) ($openEditId ?? '') === (string) $record->timecapture_format_id,
                'body' => view('timekeeping.time-capture-formats._form', [
                    'record' => $record->loadMissing('fields'),
                    'isEdit' => true,
                    'formContext' => 'edit-time-capture-format-'.$record->timecapture_format_id,
                ])->render(),
            ])
        @endcan
    @endforeach
</div>
