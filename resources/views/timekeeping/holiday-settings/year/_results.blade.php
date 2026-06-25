@php
    use App\Support\HolidaySettings as HolidaySettingsSupport;
@endphp

<div data-live-table-total-update data-total="{{ $records->total() }}" hidden></div>

<div class="datatable-skolaris-table-wrap">
    <div class="overflow-x-auto">
        <table class="table-skolaris min-w-[520px]">
            <thead>
                <tr>
                    <th>Year</th>
                    <th>Holidays</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr>
                        <td class="font-medium text-gray-900">{{ $record->timekeeping_year }}</td>
                        <td class="text-gray-600">{{ $record->holiday_years_count }}</td>
                        <td>
                            <div class="flex items-center justify-end gap-1.5">
                                @can('timekeeping-policy.update')
                                    <button type="button" data-modal-open="holiday-year-manage-{{ $record->timekeeping_year_id }}" class="btn-icon" title="Manage">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                @endcan
                                @can('timekeeping-policy.delete')
                                    <form method="POST" action="{{ route(HolidaySettingsSupport::routeName('destroy-year'), $record->timekeeping_year_id) }}" onsubmit="return confirm('Delete this year and its holiday list?')" class="inline">
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
                        <td colspan="3" class="py-12 text-center text-sm text-gray-500">No years found.</td>
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
        @php
            $yearForModal = HolidaySettingsSupport::findYearOrFail($record->timekeeping_year_id);
            $availableOptions = HolidaySettingsSupport::availableYearHolidayOptions($record->timekeeping_year_id);
        @endphp
        @can('timekeeping-policy.update')
            @include('partials.modal', [
                'id' => 'holiday-year-manage-'.$record->timekeeping_year_id,
                'title' => 'Manage Year '.$record->timekeeping_year,
                'description' => 'Update year and manage its holiday schedule',
                'panelClass' => 'modal-panel-lg',
                'open' => (string) ($openYearId ?? '') === (string) $record->timekeeping_year_id,
                'body' => view('timekeeping.holiday-settings.year._manage', [
                    'year' => $yearForModal,
                    'availableOptions' => $availableOptions,
                    'holidayOptions' => $holidayOptions,
                ])->render(),
            ])
        @endcan
    @endforeach
</div>
