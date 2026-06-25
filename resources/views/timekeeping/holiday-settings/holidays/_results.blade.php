@php
    use App\Support\HolidaySettings as HolidaySettingsSupport;
@endphp

<div data-live-table-total-update data-total="{{ $records->total() }}" hidden></div>

<div class="datatable-skolaris-table-wrap">
    <div class="overflow-x-auto">
        <table class="table-skolaris min-w-[900px]">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Recurring</th>
                    <th>Date</th>
                    <th>Legal / Special</th>
                    <th>Short Description</th>
                    <th>Description</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr>
                        <td class="font-medium text-gray-900">{{ $record->timekeeping_holiday_code }}</td>
                        <td class="text-gray-600">{{ $record->recurring ? 'Yes' : 'No' }}</td>
                        <td class="text-gray-600">{{ $record->dt_datestamp?->format('Y-m-d') }}</td>
                        <td class="text-gray-600">{{ HolidaySettingsSupport::legalLabel($record->is_legal) }}</td>
                        <td class="text-gray-600">{{ $record->short_description ?: '—' }}</td>
                        <td class="text-gray-600">{{ $record->description }}</td>
                        <td>
                            <div class="flex items-center justify-end gap-1.5">
                                @can('timekeeping-policy.update')
                                    <button type="button" data-modal-open="holiday-edit-{{ $record->timekeeping_holiday_id }}" class="btn-icon" title="Edit">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                @endcan
                                @can('timekeeping-policy.delete')
                                    <form method="POST" action="{{ route(HolidaySettingsSupport::routeName('destroy-holiday'), $record->timekeeping_holiday_id) }}" onsubmit="return confirm('Delete this holiday?')" class="inline">
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
                        <td colspan="7" class="py-12 text-center text-sm text-gray-500">No holidays found.</td>
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
                'id' => 'holiday-edit-'.$record->timekeeping_holiday_id,
                'title' => 'Edit Holiday',
                'description' => 'Update master holiday details',
                'panelClass' => 'modal-panel-md',
                'open' => (string) ($openEditId ?? '') === (string) $record->timekeeping_holiday_id,
                'body' => view('timekeeping.holiday-settings.holidays._form', [
                    'record' => $record,
                    'isEdit' => true,
                    'formContext' => 'edit-holiday-'.$record->timekeeping_holiday_id,
                    'holidayOptions' => $holidayOptions,
                ])->render(),
            ])
        @endcan
    @endforeach
</div>
